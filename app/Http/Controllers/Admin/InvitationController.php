<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListInvitationsRequest;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Requests\UpdateInvitationRequest;
use App\Http\Resources\Admin\InvitationDetailResource;
use App\Http\Resources\Admin\InvitationListResource;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Wedding;
use App\Support\InvitationAccessToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class InvitationController extends Controller
{
    public function index(ListInvitationsRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $wedding = Wedding::currentSingleWedding();

        if ($wedding === null) {
            return $this->notFound();
        }

        $data = $request->validated();
        $sortColumns = [
            'displayName' => 'display_name',
            'status' => 'status',
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
            'submittedAt' => 'submitted_at',
        ];

        $query = $this->withResponseCounts(
            $wedding->invitations()->getQuery()
        );

        if (! empty($data['search'])) {
            $search = '%'.$data['search'].'%';
            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where('display_name', 'like', $search)
                    ->orWhere('contact_person_name', 'like', $search)
                    ->orWhere('contact_number', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhereHas('guests', fn (Builder $guests) => $guests->where('full_name', 'like', $search));
            });
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $query->orderBy(
            $sortColumns[$data['sortBy'] ?? 'createdAt'],
            $data['sortDirection'] ?? 'desc',
        );

        return InvitationListResource::collection(
            $query->paginate($data['perPage'] ?? 20)
        );
    }

    public function store(
        StoreInvitationRequest $request,
        CreateInvitation $createInvitation,
        InvitationAccessToken $tokens,
    ): JsonResponse {
        $wedding = Wedding::currentSingleWedding();

        if ($wedding === null) {
            return $this->notFound();
        }

        [$invitation, $rawToken] = $createInvitation->handle($wedding, $request->validated());

        return response()->json([
            'data' => [
                'invitation' => $this->detailResource($invitation)->resolve($request),
                'access' => [
                    'token' => $rawToken,
                    'invitationUrl' => $tokens->url($rawToken),
                ],
            ],
        ], 201);
    }

    public function show(int $invitation): InvitationDetailResource|JsonResponse
    {
        $record = $this->findInvitation($invitation);

        return $record ? $this->detailResource($record) : $this->notFound();
    }

    public function update(
        UpdateInvitationRequest $request,
        CreateInvitation $mapper,
        int $invitation,
    ): InvitationDetailResource|JsonResponse {
        $record = $this->findInvitation($invitation);

        if ($record === null) {
            return $this->notFound();
        }

        $record->update($mapper->profile($request->validated()));

        return $this->detailResource($record->refresh());
    }

    public function destroy(int $invitation): JsonResponse
    {
        $record = $this->findInvitation($invitation);

        if ($record === null) {
            return $this->notFound();
        }

        if (in_array($record->status, [
            Invitation::STATUS_SUBMITTED,
            Invitation::STATUS_LOCKED,
            Invitation::STATUS_ARCHIVED,
        ], true)) {
            return $this->conflict('Submitted, locked, or archived invitations cannot be deleted.');
        }

        $record->delete();

        return response()->json(status: 204);
    }

    public function markReady(int $invitation): InvitationDetailResource|JsonResponse
    {
        $record = $this->findInvitation($invitation);

        if ($record === null) {
            return $this->notFound();
        }

        if ($record->status === Invitation::STATUS_READY) {
            return $this->detailResource($record);
        }

        if ($record->status !== Invitation::STATUS_DRAFT || ! $record->guests()->exists()) {
            return $this->conflict('Only draft invitations with at least one guest can be marked ready.');
        }

        $record->update(['status' => Invitation::STATUS_READY]);

        return $this->detailResource($record->refresh());
    }

    public function lock(int $invitation): InvitationDetailResource|JsonResponse
    {
        $record = $this->findInvitation($invitation);

        if ($record === null) {
            return $this->notFound();
        }

        if ($record->status === Invitation::STATUS_LOCKED) {
            return $this->detailResource($record);
        }

        if (! in_array($record->status, [Invitation::STATUS_READY, Invitation::STATUS_SUBMITTED], true)) {
            return $this->conflict('Only ready or submitted invitations can be locked.');
        }

        $record->update([
            'status' => Invitation::STATUS_LOCKED,
            'locked_at' => $record->locked_at ?? now(),
        ]);

        return $this->detailResource($record->refresh());
    }

    public function reopen(int $invitation): InvitationDetailResource|JsonResponse
    {
        $record = $this->findInvitation($invitation);

        if ($record === null) {
            return $this->notFound();
        }

        if ($record->status !== Invitation::STATUS_LOCKED) {
            return $this->conflict('Only locked invitations can be reopened.');
        }

        $record->update([
            'status' => $record->submitted_at
                ? Invitation::STATUS_SUBMITTED
                : Invitation::STATUS_READY,
            'locked_at' => null,
        ]);

        return $this->detailResource($record->refresh());
    }

    public function archive(int $invitation): InvitationDetailResource|JsonResponse
    {
        $record = $this->findInvitation($invitation);

        if ($record === null) {
            return $this->notFound();
        }

        if ($record->status !== Invitation::STATUS_ARCHIVED) {
            $record->update(['status' => Invitation::STATUS_ARCHIVED]);
        }

        return $this->detailResource($record->refresh());
    }

    public function regenerateToken(
        InvitationAccessToken $tokens,
        int $invitation,
    ): JsonResponse {
        $record = $this->findInvitation($invitation);

        if ($record === null) {
            return $this->notFound();
        }

        if ($record->status === Invitation::STATUS_ARCHIVED) {
            return $this->conflict('Archived invitations cannot regenerate access tokens.');
        }

        $rawToken = DB::transaction(function () use ($record, $tokens) {
            $rawToken = $tokens->generate();
            $record->update(['token_hash' => $tokens->hash($rawToken)]);

            return $rawToken;
        });

        return response()->json([
            'data' => [
                'invitationId' => $record->id,
                'access' => [
                    'token' => $rawToken,
                    'invitationUrl' => $tokens->url($rawToken),
                ],
            ],
        ]);
    }

    private function findInvitation(int $id): ?Invitation
    {
        $wedding = Wedding::currentSingleWedding();

        return $wedding?->invitations()->whereKey($id)->first();
    }

    private function detailResource(Invitation $invitation): InvitationDetailResource
    {
        $invitation->load([
            'guests' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        $counted = $this->withResponseCounts($invitation->newQuery()->whereKey($invitation->id))->firstOrFail();
        $counted->setRelations($invitation->getRelations());

        return new InvitationDetailResource($counted);
    }

    private function withResponseCounts(Builder $query): Builder
    {
        return $query->withCount([
            'guests as guest_count',
            'guests as attending_count' => fn (Builder $query) => $query->where('attendance_status', Guest::ATTENDANCE_ATTENDING),
            'guests as declined_count' => fn (Builder $query) => $query->where('attendance_status', Guest::ATTENDANCE_DECLINED),
            'guests as pending_count' => fn (Builder $query) => $query->where('attendance_status', Guest::ATTENDANCE_PENDING),
        ]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Invitation not found.'], 404);
    }

    private function conflict(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 409);
    }
}
