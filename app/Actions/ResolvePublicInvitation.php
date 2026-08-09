<?php

namespace App\Actions;

use App\Models\Invitation;
use App\Models\Wedding;
use App\Support\InvitationAccessToken;
use Illuminate\Support\Facades\DB;

class ResolvePublicInvitation
{
    public function __construct(private InvitationAccessToken $tokens) {}

    public function handle(string $rawToken): ?Invitation
    {
        $wedding = Wedding::currentSingleWedding();

        if ($wedding === null || $wedding->status !== Wedding::STATUS_PUBLISHED) {
            return null;
        }

        $invitation = $this->find($wedding, $rawToken);

        if ($invitation === null) {
            return null;
        }

        $openedAt = now();

        DB::transaction(function () use ($invitation, $openedAt) {
            DB::update(
                'update invitations set first_opened_at = coalesce(first_opened_at, ?), last_opened_at = ?, updated_at = ? where id = ?',
                [$openedAt, $openedAt, $openedAt, $invitation->id],
            );
        });

        $invitation->refresh();
        $invitation->setRelation('wedding', $wedding);
        $invitation->load([
            'guests' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);
        $wedding->loadMissing('rsvpQuestions');

        return $invitation;
    }

    public function handleForUpdate(string $rawToken): ?Invitation
    {
        $wedding = Wedding::query()->oldest('id')->lockForUpdate()->first();

        if ($wedding === null || $wedding->status !== Wedding::STATUS_PUBLISHED) {
            return null;
        }

        $invitation = $this->find($wedding, $rawToken, true);
        $wedding->loadMissing('rsvpQuestions');
        $invitation?->setRelation('wedding', $wedding);

        return $invitation;
    }

    private function find(Wedding $wedding, string $rawToken, bool $lockForUpdate = false): ?Invitation
    {
        if (! preg_match('/\A[A-Za-z0-9_-]{32,128}\z/', $rawToken)) {
            return null;
        }

        return $wedding->invitations()
            ->where('token_hash', $this->tokens->hash($rawToken))
            ->whereIn('status', [
                Invitation::STATUS_READY,
                Invitation::STATUS_SUBMITTED,
                Invitation::STATUS_LOCKED,
            ])
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();
    }
}
