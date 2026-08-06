<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuestRequest;
use App\Http\Resources\Admin\GuestResource;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GuestController extends Controller
{
    public function store(
        GuestRequest $request,
        CreateInvitation $mapper,
        int $invitation,
    ): GuestResource|JsonResponse {
        return DB::transaction(function () use ($request, $mapper, $invitation) {
            $record = $this->findInvitation($invitation, true);

            if ($record === null) {
                return $this->notFound();
            }

            if ($record->status === Invitation::STATUS_ARCHIVED) {
                return $this->conflict('Archived invitations cannot be modified.');
            }

            $guest = $record->guests()->create([
                ...$mapper->guest($request->validated()),
                'attendance_status' => Guest::ATTENDANCE_PENDING,
            ]);

            return (new GuestResource($guest))->response()->setStatusCode(201);
        });
    }

    public function update(
        GuestRequest $request,
        CreateInvitation $mapper,
        int $invitation,
        int $guest,
    ): GuestResource|JsonResponse {
        return DB::transaction(function () use ($request, $mapper, $invitation, $guest) {
            [$record, $guestRecord] = $this->findNested($invitation, $guest, true);

            if ($record === null || $guestRecord === null) {
                return $this->notFound();
            }

            if ($record->status === Invitation::STATUS_ARCHIVED) {
                return $this->conflict('Archived invitations cannot be modified.');
            }

            $guestRecord->update($mapper->guest($request->validated()));

            return new GuestResource($guestRecord->refresh());
        });
    }

    public function destroy(int $invitation, int $guest): JsonResponse
    {
        return DB::transaction(function () use ($invitation, $guest) {
            [$record, $guestRecord] = $this->findNested($invitation, $guest, true);

            if ($record === null || $guestRecord === null) {
                return $this->notFound();
            }

            if ($record->status === Invitation::STATUS_ARCHIVED) {
                return $this->conflict('Archived invitations cannot be modified.');
            }

            if ($guestRecord->attendance_status !== Guest::ATTENDANCE_PENDING) {
                return $this->conflict('Only guests with pending attendance can be deleted.');
            }

            if ($record->guests()->count() <= 1) {
                return $this->conflict('An invitation must retain at least one guest.');
            }

            $guestRecord->delete();

            return response()->json(status: 204);
        });
    }

    private function findInvitation(int $id, bool $lockForUpdate = false): ?Invitation
    {
        return Wedding::currentSingleWedding()?->invitations()
            ->whereKey($id)
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    private function findNested(int $invitation, int $guest, bool $lockForUpdate = false): array
    {
        $record = $this->findInvitation($invitation, $lockForUpdate);

        return [
            $record,
            $record?->guests()
                ->whereKey($guest)
                ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
                ->first(),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Invitation or guest not found.'], 404);
    }

    private function conflict(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 409);
    }
}
