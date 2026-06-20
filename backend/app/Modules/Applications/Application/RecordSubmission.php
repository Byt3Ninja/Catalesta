<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application;

use App\Modules\Applications\Application\Exceptions\SubmissionTooLargeException;
use App\Modules\Applications\Application\Exceptions\UnknownBlobReferenceException;
use App\Modules\Applications\Domain\Models\ApplicationSubmission;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Shared\Storage\ContentAddressedStore;
use Illuminate\Support\Facades\DB;

/**
 * Records an application submission as an immutable snapshot bound to a cohort.
 *
 * The version ids (resolved form/program/rubric) and blob digests are supplied by
 * the caller (the public submit endpoint, Story 2.7, resolves the open cohort +
 * published-form version). Each referenced blob is pinned (incrementRef) inside
 * the same transaction as the snapshot write, so blobs:gc (Story 2.1) can never
 * collect a blob a snapshot points at; an unknown digest is rejected.
 */
final class RecordSubmission
{
    /** Max serialized answers payload; fail-closed beyond this (AC-8). */
    private const MAX_PAYLOAD_BYTES = 1_048_576; // 1 MiB

    public function __construct(private readonly ContentAddressedStore $blobs) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<int, string>  $blobDigests
     * @param  array<string, string|null>  $versionIds  keys: form, program, rubric (any may be null/absent)
     */
    public function handle(Cohort $cohort, array $answers, array $blobDigests, array $versionIds): ApplicationSubmission
    {
        $encoded = json_encode($answers, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            throw new SubmissionTooLargeException(strlen($encoded), self::MAX_PAYLOAD_BYTES);
        }

        return DB::transaction(function () use ($cohort, $answers, $blobDigests, $versionIds): ApplicationSubmission {
            // Pin every referenced blob before the snapshot is durable (AC-5/6).
            // Reject unknown/half-uploaded digests; rollback undoes any increments.
            foreach ($blobDigests as $digest) {
                if (! $this->blobs->exists($digest)) {
                    throw new UnknownBlobReferenceException($digest);
                }
                $this->blobs->incrementRef($digest);
            }

            // The submission belongs to the COHORT's org, not the caller's tenant.
            // A public applicant (Story 2.7) has no TenantContext, so the org is
            // set explicitly from the cohort — BelongsToTenant's "explicit org"
            // path permits this when no tenant is resolved, and forces the same
            // value when one is (the cohort is in that tenant), so 2.6's
            // tenant-context callers are unaffected.
            $submission = new ApplicationSubmission([
                'cohort_id' => $cohort->id,
                'submission_snapshot' => [
                    'answers' => $answers,
                    'blob_refs' => array_values($blobDigests),
                    'form_version_id' => $versionIds['form'] ?? null,
                    'program_version_id' => $versionIds['program'] ?? null,
                    'rubric_version_id' => $versionIds['rubric'] ?? null,
                ],
            ]);
            $submission->setAttribute('organization_id', $cohort->organization_id);
            $submission->save();

            return $submission;
        });
    }
}
