<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application;

use App\Modules\Applications\Application\Exceptions\CohortClosedException;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use App\Shared\Idempotency\IdempotencyService;
use App\Shared\Idempotency\RequestFingerprint;
use App\Shared\Outbox\OutboxProducer;
use App\Shared\Storage\ContentAddressedStore;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * The public application submit (Story 2.7) — the engine the stepped UI calls.
 *
 * Wraps the whole operation in {@see IdempotencyService}: a duplicate
 * Idempotency-Key replays the original receipt and never writes a second record
 * (FR-032). Inside ONE transaction it re-checks the cohort is open under a row
 * lock — so a CloseCohort racing the submit still wins (FR-033) — then records
 * the immutable snapshot (2.6), pins blobs, emits ApplicationSubmitted to the
 * outbox (2.3), and audits (2.5). The applicant has no TenantContext, so the
 * cohort is resolved (and the submission written) under system context with the
 * org taken from the cohort.
 */
final class SubmitApplication
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly OutboxProducer $outbox,
        private readonly RecordSubmission $record,
        private readonly AuditLogger $audit,
        private readonly ContentAddressedStore $blobs,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<int, string>  $uploads  raw file contents to store content-addressed
     * @param  array<int, string>  $blobDigests  already-stored blob refs to reference
     * @return array{reference_number: string, status: string, cohort_id: string, submitted_at: ?string}
     */
    public function handle(
        string $cohortId,
        string $idempotencyKey,
        string $actor,
        array $answers,
        array $uploads = [],
        array $blobDigests = [],
    ): array {
        // Pre-hash uploads to fingerprint WITHOUT storing — a replay (completed
        // key) must not re-store. Actual storage happens inside the transaction.
        $uploadDigests = array_map(static fn (string $c): string => hash('sha256', $c), $uploads);
        $allDigests = array_values(array_unique([...$blobDigests, ...$uploadDigests]));
        sort($allDigests); // order-independent fingerprint

        $fingerprint = RequestFingerprint::for($actor, [
            'cohort' => $cohortId,
            'answers' => $answers,
            'blobs' => $allDigests,
        ]);

        /** @var array{reference_number: string, status: string, cohort_id: string, submitted_at: ?string} $receipt */
        $receipt = $this->idempotency->remember(
            'application.submit:'.$cohortId,
            $idempotencyKey,
            $fingerprint,
            fn (): array => $this->tenant->runAsSystem(function () use ($cohortId, $answers, $uploads, $allDigests): array {
                // Store uploads to the (MinIO) blob store BEFORE the transaction.
                // A bucket put is not transactional, so doing it inside the txn
                // would orphan the object on rollback — the Blob row rolls back,
                // the bucket object does not, and refcount GC can never see it.
                // Content-addressed, so this is idempotent; it runs once per real
                // attempt (the idempotency layer skips it on a completed replay).
                foreach ($uploads as $contents) {
                    $this->blobs->store($contents);
                }

                return DB::transaction(fn (): array => $this->write($cohortId, $answers, $allDigests));
            }),
        );

        return $receipt;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<int, string>  $allDigests  already-stored blob digests to pin
     * @return array{reference_number: string, status: string, cohort_id: string, submitted_at: ?string}
     */
    private function write(string $cohortId, array $answers, array $allDigests): array
    {
        // Authoritative open-check INSIDE the write, under a row lock, so a
        // concurrent CloseCohort serializes against us (FR-033 close race).
        $cohort = Cohort::lockForUpdate()->find($cohortId);
        if ($cohort === null || ! $cohort->isAcceptingSubmissions()) {
            throw new CohortClosedException($cohortId);
        }

        // Uploads were stored before the transaction; RecordSubmission pins every
        // digest in $allDigests so referenced blobs survive GC.
        $submission = $this->record->handle($cohort, $answers, $allDigests, [
            'form' => $cohort->form_version_id,
            // program/rubric version ids will resolve from the form version once
            // that link exists (1.3 stores only the form version on the cohort).
            'program' => null,
            'rubric' => null,
        ])->refresh(); // load DB-set created_at for the receipt

        $this->outbox->record('ApplicationSubmitted', [
            'submission_id' => $submission->id,
            'cohort_id' => $cohort->id,
            'organization_id' => $cohort->organization_id,
            'form_version_id' => $cohort->form_version_id,
        ]);

        $this->audit->record(
            AuditAction::ApplicationSubmitted->value,
            'application_submission',
            $submission->id,
            [],
            [],
            'success',
            $cohort->organization_id,
        );

        return [
            'reference_number' => $submission->id,
            'status' => 'received',
            'cohort_id' => $cohort->id,
            'submitted_at' => $submission->created_at?->toIso8601String(),
        ];
    }
}
