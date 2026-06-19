<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http;

use App\Modules\Programs\Application\DeleteTrack;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\Track;
use App\Modules\Programs\Http\Requests\StoreTrackRequest;
use App\Modules\Programs\Http\Requests\UpdateTrackRequest;
use App\Modules\Programs\Http\Resources\TrackResource;
use App\Shared\Audit\AuditLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class TrackController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/v1/programs/{program}/tracks
     *
     * List all tracks for a program. BelongsToTenant scope on Program ensures
     * cross-tenant {program} ids result in findOrFail throwing 404.
     */
    public function index(string $program): AnonymousResourceCollection
    {
        $prog = Program::query()->findOrFail($program);

        $this->authorize('manageTracks', $prog);

        $tracks = Track::query()
            ->where('program_id', $prog->id)
            ->orderBy('order_index')
            ->get();

        return TrackResource::collection($tracks);
    }

    /**
     * POST /api/v1/programs/{program}/tracks
     *
     * Create a new track under a program.
     * organization_id is auto-stamped by BelongsToTenant creating hook.
     */
    public function store(StoreTrackRequest $request, AuditLogger $audit, string $program): JsonResponse
    {
        $prog = Program::query()->findOrFail($program);

        $this->authorize('manageTracks', $prog);

        /** @var array{key: string, name: string, description?: string|null, order_index?: int} $data */
        $data = $request->validated();

        $track = Track::create(array_merge($data, [
            'program_id' => $prog->id,
            'organization_id' => $prog->organization_id,
        ]));

        $audit->record(
            'track.created',
            'track',
            $track->id,
            [],
            ['key' => $track->key, 'name' => $track->name, 'program_id' => $track->program_id],
        );

        return (new TrackResource($track))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /api/v1/tracks/{id}
     *
     * Update a track's name, description, and/or order_index.
     * BelongsToTenant global scope on Track ensures cross-tenant ids 404.
     */
    public function update(UpdateTrackRequest $request, AuditLogger $audit, string $id): TrackResource
    {
        $track = Track::query()->findOrFail($id);

        $this->authorize('manageTracks', $track);

        /** @var array{name?: string, description?: string|null, order_index?: int} $data */
        $data = $request->validated();

        $before = $track->only(['name', 'description', 'order_index']);

        if (isset($data['name'])) {
            $track->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $track->description = $data['description'];
        }

        if (isset($data['order_index'])) {
            $track->order_index = $data['order_index'];
        }

        $track->save();

        $after = $track->only(['name', 'description', 'order_index']);

        $audit->record(
            'track.updated',
            'track',
            $track->id,
            $before,
            $after,
        );

        return new TrackResource($track);
    }

    /**
     * DELETE /api/v1/tracks/{id}
     *
     * Delete a track (and cascade — see DeleteTrack service for Tasks 4/5 extensions).
     * BelongsToTenant global scope on Track ensures cross-tenant ids 404.
     */
    public function destroy(DeleteTrack $service, AuditLogger $audit, string $id): Response
    {
        $track = Track::query()->findOrFail($id);

        $this->authorize('manageTracks', $track);

        $audit->record(
            'track.deleted',
            'track',
            $track->id,
            ['key' => $track->key, 'name' => $track->name, 'program_id' => $track->program_id],
            [],
        );

        $service->handle($track);

        return response()->noContent();
    }
}
