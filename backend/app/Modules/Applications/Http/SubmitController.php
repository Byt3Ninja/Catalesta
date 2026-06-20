<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http;

use App\Modules\Applications\Application\SubmitApplication;
use App\Modules\Applications\Http\Requests\SubmitApplicationRequest;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public application submit (Story 2.7). Authenticated applicant (`sub`), NOT
 * behind the `tenant` middleware — the applicant has no org. Thin: resolves the
 * cohort across tenants for a clean 404 (distinct from the 422 a closed cohort
 * returns) and delegates the idempotent, transactional write to
 * {@see SubmitApplication}.
 */
final class SubmitController
{
    public function __construct(private readonly SubmitApplication $submit) {}

    public function store(SubmitApplicationRequest $request, string $cohort): JsonResponse
    {
        /** @var ExternalUser $user */
        $user = $request->user();

        // A cohort is public once opened, so resolve it under system context. An
        // unknown id is 404; a known-but-closed cohort is 422 (decided in the txn).
        $exists = app(TenantContext::class)->runAsSystem(
            static fn (): bool => Cohort::query()->whereKey($cohort)->exists(),
        );
        if (! $exists) {
            throw new NotFoundHttpException('Cohort not found.');
        }

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('files', []);
        $uploads = array_map(static fn (UploadedFile $f): string => $f->getContent(), $files);

        $receipt = $this->submit->handle(
            cohortId: $cohort,
            idempotencyKey: (string) $request->string('idempotency_key'),
            actor: (string) $user->startup_gate_subject_id,
            answers: $request->array('answers'),
            uploads: $uploads,
            blobDigests: $request->array('blob_digests'),
        );

        return response()->json($receipt, 201);
    }
}
