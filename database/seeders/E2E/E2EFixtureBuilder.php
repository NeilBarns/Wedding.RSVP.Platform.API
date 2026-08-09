<?php

namespace Database\Seeders\E2E;

use App\Actions\CreateInvitation;
use App\Actions\SubmitPublicRsvp;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Wedding;
use App\Support\InvitationAccessToken;

class E2EFixtureBuilder
{
    public const ADMIN_EMAIL = 'e2e-owner@example.test';

    public const ADMIN_PASSWORD = 'E2E-only-password';

    public function __construct(
        private CreateInvitation $createInvitation,
        private SubmitPublicRsvp $submitRsvp,
        private InvitationAccessToken $tokens,
    ) {}

    public function build(): array
    {
        $owner = User::query()->create([
            'name' => 'E2E Owner',
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
            'role' => User::ROLE_OWNER,
        ]);

        $wedding = Wedding::query()->create([
            'partner_one_name' => 'E2E Partner One',
            'partner_two_name' => 'E2E Partner Two',
            'wedding_date' => '2099-06-21',
            'rsvp_deadline' => '2099-05-31',
            'dress_code' => 'E2E Formal',
            'status' => Wedding::STATUS_PUBLISHED,
            'theme_key' => 'e2e-linen',
            'primary_color' => '#4A5D4E',
            'secondary_color' => '#D8CBB8',
            'accent_color' => '#9A6B4F',
            'background_color' => '#F5F1EA',
            'heading_font' => 'Georgia',
            'body_font' => 'Inter',
        ]);

        $this->createContent($wedding);
        [$fresh, $freshToken] = $this->createHousehold($wedding, 'E2E Fresh Household', [
            ['fullName' => 'E2E Guest One', 'guestType' => Guest::TYPE_ADULT, 'sortOrder' => 0],
            ['fullName' => 'E2E Guest Two', 'guestType' => Guest::TYPE_CHILD, 'sortOrder' => 1],
        ]);
        [$submitted, $submittedToken] = $this->createHousehold($wedding, 'E2E Submitted Household', [
            ['fullName' => 'E2E Submitted Attending', 'guestType' => Guest::TYPE_ADULT, 'sortOrder' => 0],
            ['fullName' => 'E2E Submitted Declined', 'guestType' => Guest::TYPE_ADULT, 'sortOrder' => 1],
        ]);
        $submittedGuests = $submitted->guests()->orderBy('sort_order')->get();
        $this->submitRsvp->handle($submittedToken, [
            'guests' => [
                ['id' => $submittedGuests[0]->id, 'attendanceStatus' => Guest::ATTENDANCE_ATTENDING, 'dietaryRequirements' => 'E2E vegetarian', 'accessibilityRequirements' => null],
                ['id' => $submittedGuests[1]->id, 'attendanceStatus' => Guest::ATTENDANCE_DECLINED, 'dietaryRequirements' => null, 'accessibilityRequirements' => null],
            ],
            'contactNumber' => '+63 900 000 0000',
            'email' => 'submitted-household@example.test',
            'message' => 'E2E submitted response message.',
        ]);
        $submitted->refresh();

        [$locked, $lockedToken] = $this->createHousehold($wedding, 'E2E Locked Household', [
            ['fullName' => 'E2E Locked Guest', 'guestType' => Guest::TYPE_ADULT, 'sortOrder' => 0],
        ]);
        $locked->update(['status' => Invitation::STATUS_LOCKED, 'locked_at' => now()]);

        return [
            'admin' => ['id' => $owner->id, 'name' => $owner->name, 'email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD, 'role' => $owner->role],
            'wedding' => ['id' => $wedding->id, 'status' => $wedding->status],
            'invitations' => [
                'fresh' => $this->invitationSummary($fresh, $freshToken),
                'submitted' => $this->invitationSummary($submitted, $submittedToken),
                'locked' => $this->invitationSummary($locked, $lockedToken),
            ],
        ];
    }

    private function createHousehold(Wedding $wedding, string $displayName, array $guests): array
    {
        [$invitation, $token] = $this->createInvitation->handle($wedding, ['displayName' => $displayName, 'guests' => $guests]);
        $invitation->update(['status' => Invitation::STATUS_READY]);

        return [$invitation->refresh(), $token];
    }

    private function invitationSummary(Invitation $invitation, string $token): array
    {
        return ['id' => $invitation->id, 'displayName' => $invitation->display_name, 'status' => $invitation->status, 'token' => $token, 'url' => $this->tokens->url($token)];
    }

    private function createContent(Wedding $wedding): void
    {
        $wedding->heroContent()->create(['eyebrow' => 'E2E Published Eyebrow', 'headline' => 'E2E Wedding Headline', 'subheadline' => 'E2E published hero subheadline.', 'is_published' => true]);
        $wedding->storyEntries()->createMany([
            ['title' => 'E2E Published Story', 'body' => 'E2E published story body.', 'event_date' => '2020-01-02', 'sort_order' => 1, 'is_published' => true],
            ['title' => 'E2E Draft Story', 'body' => 'E2E unpublished story body.', 'sort_order' => 2, 'is_published' => false],
        ]);
        $wedding->events()->createMany([
            ['title' => 'E2E Ceremony', 'event_type' => 'ceremony', 'event_date' => '2099-06-21', 'start_time' => '15:00', 'end_time' => '16:00', 'venue_name' => 'E2E Ceremony Venue', 'address_line' => 'E2E Ceremony Address', 'sort_order' => 1, 'is_published' => true],
            ['title' => 'E2E Reception', 'event_type' => 'reception', 'event_date' => '2099-06-21', 'start_time' => '17:00', 'venue_name' => 'E2E Reception Venue', 'sort_order' => 2, 'is_published' => true],
            ['title' => 'E2E Draft Event', 'event_type' => 'other', 'event_date' => '2099-06-20', 'sort_order' => 3, 'is_published' => false],
        ]);
        $wedding->faqEntries()->createMany([
            ['question' => 'E2E Published Question?', 'answer' => 'E2E published FAQ answer.', 'sort_order' => 1, 'is_published' => true],
            ['question' => 'E2E Draft Question?', 'answer' => 'E2E unpublished FAQ answer.', 'sort_order' => 2, 'is_published' => false],
        ]);
        $wedding->galleryEntries()->createMany([
            ['image_url' => 'http://localhost:5173/e2e-gallery-published.jpg', 'alt_text' => 'E2E published gallery image', 'caption' => 'E2E Published Gallery', 'sort_order' => 1, 'is_published' => true],
            ['image_url' => 'http://localhost:5173/e2e-gallery-draft.jpg', 'alt_text' => 'E2E draft gallery image', 'caption' => 'E2E Draft Gallery', 'sort_order' => 2, 'is_published' => false],
        ]);
    }
}
