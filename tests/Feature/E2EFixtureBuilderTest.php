<?php

namespace Tests\Feature;

use App\Enums\WeddingTemplateKey;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\RsvpRevision;
use App\Models\User;
use App\Models\Wedding;
use Database\Seeders\E2E\E2EFixtureBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class E2EFixtureBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_command_refuses_before_destructive_work_in_a_non_e2e_environment(): void
    {
        $user = User::factory()->create();

        $this->artisan('e2e:reset')
            ->expectsOutput('Refusing to reset E2E fixtures because APP_ENV is not "e2e".')
            ->assertFailed();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_builder_creates_consistent_disposable_fixture_graph(): void
    {
        $summary = app(E2EFixtureBuilder::class)->build();

        $owner = User::query()->where('email', E2EFixtureBuilder::ADMIN_EMAIL)->sole();
        $this->assertSame(User::ROLE_OWNER, $owner->role);
        $this->assertTrue(Hash::check(E2EFixtureBuilder::ADMIN_PASSWORD, $owner->password));

        $wedding = Wedding::query()->sole();
        $this->assertSame(Wedding::STATUS_PUBLISHED, $wedding->status);
        $this->assertSame(WeddingTemplateKey::EditorialLinenV1->value, $wedding->template_key);
        $this->assertTrue($wedding->heroContent()->where('is_published', true)->exists());
        $this->assertSame(2, $wedding->storyEntries()->count());
        $this->assertSame(3, $wedding->events()->count());
        $this->assertSame(2, $wedding->faqEntries()->count());
        $this->assertSame(2, $wedding->galleryEntries()->count());
        $this->assertTrue($wedding->storyEntries()->where('is_published', false)->exists());

        $fresh = Invitation::query()->where('display_name', 'E2E Fresh Household')->sole();
        $submitted = Invitation::query()->where('display_name', 'E2E Submitted Household')->sole();
        $locked = Invitation::query()->where('display_name', 'E2E Locked Household')->sole();
        $this->assertSame(Invitation::STATUS_READY, $fresh->status);
        $this->assertSame(Invitation::STATUS_SUBMITTED, $submitted->status);
        $this->assertSame(Invitation::STATUS_LOCKED, $locked->status);
        $this->assertNotNull($locked->locked_at);
        $this->assertSame([Guest::ATTENDANCE_ATTENDING, Guest::ATTENDANCE_DECLINED], $submitted->guests()->orderBy('sort_order')->pluck('attendance_status')->all());
        $this->assertSame(1, $submitted->rsvpRevisions()->count());
        $this->assertSame(1, RsvpRevision::query()->sole()->revision_number);

        foreach (['fresh', 'submitted', 'locked'] as $fixture) {
            $token = $summary['invitations'][$fixture]['token'];
            $invitation = Invitation::query()->findOrFail($summary['invitations'][$fixture]['id']);
            $this->assertSame(hash('sha256', $token), $invitation->token_hash);
            $this->assertNotSame($token, $invitation->token_hash);
            $this->assertStringEndsWith('/invite/'.$token, $summary['invitations'][$fixture]['url']);
        }

        $this->assertSame(E2EFixtureBuilder::ADMIN_EMAIL, $summary['admin']['email']);
        $this->assertSame($wedding->id, $summary['wedding']['id']);
    }
}
