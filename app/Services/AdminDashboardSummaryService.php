<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Wedding;

class AdminDashboardSummaryService
{
    /**
     * @return array{
     *     weddingId: int,
     *     weddingStatus: string,
     *     invitations: array{total: int, draft: int, ready: int, submitted: int, locked: int, archived: int},
     *     guests: array{total: int, attending: int, declined: int, pending: int},
     *     households: array{submitted: int}
     * }
     */
    public function summarize(Wedding $wedding): array
    {
        $invitationCounts = $wedding->invitations()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $guestCounts = Guest::query()
            ->whereHas('invitation', fn ($query) => $query->where('wedding_id', $wedding->id))
            ->selectRaw('attendance_status, COUNT(*) as aggregate')
            ->groupBy('attendance_status')
            ->pluck('aggregate', 'attendance_status');

        $invitations = $this->countsFor(Invitation::STATUSES, $invitationCounts->all());
        $guests = $this->countsFor([
            Guest::ATTENDANCE_ATTENDING,
            Guest::ATTENDANCE_DECLINED,
            Guest::ATTENDANCE_PENDING,
        ], $guestCounts->all());

        return [
            'weddingId' => $wedding->id,
            'weddingStatus' => $wedding->status,
            'invitations' => [
                'total' => array_sum($invitations),
                ...$invitations,
            ],
            'guests' => [
                'total' => array_sum($guests),
                ...$guests,
            ],
            'households' => [
                'submitted' => $invitations[Invitation::STATUS_SUBMITTED],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $statuses
     * @param  array<string, int|string>  $counts
     * @return array<string, int>
     */
    private function countsFor(array $statuses, array $counts): array
    {
        return array_combine(
            $statuses,
            array_map(fn (string $status): int => (int) ($counts[$status] ?? 0), $statuses),
        );
    }
}
