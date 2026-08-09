<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_requires_authentication_and_returns_not_found_without_a_wedding(): void
    {
        $this->getJson('/api/admin/dashboard/summary')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/dashboard/summary')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Wedding not found.']);
    }

    public function test_owner_and_administrator_can_access_the_summary(): void
    {
        $wedding = Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED]);

        foreach ([User::ROLE_OWNER, User::ROLE_ADMINISTRATOR] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/dashboard/summary')
                ->assertOk()
                ->assertJsonPath('data.weddingId', $wedding->id)
                ->assertJsonPath('data.weddingStatus', Wedding::STATUS_PUBLISHED);
        }
    }

    public function test_summary_returns_authoritative_status_and_attendance_counts_for_the_current_wedding(): void
    {
        $wedding = Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED]);
        $otherWedding = Wedding::factory()->create();

        $draft = Invitation::factory()->for($wedding)->create(['status' => Invitation::STATUS_DRAFT]);
        $ready = Invitation::factory()->for($wedding)->create(['status' => Invitation::STATUS_READY]);
        $submitted = Invitation::factory()->for($wedding)->submitted()->create();
        $locked = Invitation::factory()->for($wedding)->locked()->create();
        $archived = Invitation::factory()->for($wedding)->create(['status' => Invitation::STATUS_ARCHIVED]);

        Guest::factory()->for($draft)->attending()->count(2)->create();
        Guest::factory()->for($ready)->declined()->create();
        Guest::factory()->for($submitted)->count(3)->create();
        Guest::factory()->for($locked)->attending()->create();
        Guest::factory()->for($archived)->declined()->count(2)->create();

        $unrelatedInvitation = Invitation::factory()->for($otherWedding)->submitted()->create();
        Guest::factory()->for($unrelatedInvitation)->attending()->count(4)->create();

        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->getJson('/api/admin/dashboard/summary')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'weddingId' => $wedding->id,
                    'weddingStatus' => Wedding::STATUS_PUBLISHED,
                    'invitations' => [
                        'total' => 5,
                        'draft' => 1,
                        'ready' => 1,
                        'submitted' => 1,
                        'locked' => 1,
                        'archived' => 1,
                    ],
                    'guests' => [
                        'total' => 9,
                        'attending' => 3,
                        'declined' => 3,
                        'pending' => 3,
                    ],
                    'households' => [
                        'submitted' => 1,
                    ],
                ],
            ]);

        $invitationCounts = $response->json('data.invitations');
        $guestCounts = $response->json('data.guests');

        $this->assertSame(
            $invitationCounts['total'],
            $invitationCounts['draft'] + $invitationCounts['ready'] + $invitationCounts['submitted'] + $invitationCounts['locked'] + $invitationCounts['archived'],
        );
        $this->assertSame(
            $guestCounts['total'],
            $guestCounts['attending'] + $guestCounts['declined'] + $guestCounts['pending'],
        );
    }

    public function test_zero_invitation_wedding_returns_all_zero_counts(): void
    {
        $wedding = Wedding::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.weddingId', $wedding->id)
            ->assertJsonPath('data.invitations', [
                'total' => 0,
                'draft' => 0,
                'ready' => 0,
                'submitted' => 0,
                'locked' => 0,
                'archived' => 0,
            ])
            ->assertJsonPath('data.guests', [
                'total' => 0,
                'attending' => 0,
                'declined' => 0,
                'pending' => 0,
            ])
            ->assertJsonPath('data.households.submitted', 0);
    }

    public function test_invitations_without_guests_return_zero_guest_counts(): void
    {
        $wedding = Wedding::factory()->create();
        Invitation::factory()->for($wedding)->ready()->count(2)->create();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.invitations.total', 2)
            ->assertJsonPath('data.guests', [
                'total' => 0,
                'attending' => 0,
                'declined' => 0,
                'pending' => 0,
            ]);
    }
}
