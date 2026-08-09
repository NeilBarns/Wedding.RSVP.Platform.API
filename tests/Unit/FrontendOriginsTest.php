<?php

namespace Tests\Unit;

use App\Support\FrontendOrigins;
use App\Support\InvitationAccessToken;
use Tests\TestCase;

class FrontendOriginsTest extends TestCase
{
    public function test_comma_separated_origins_are_normalized_and_deduplicated(): void
    {
        $origins = FrontendOrigins::parse(
            ' http://localhost:5173/, http://127.0.0.1:5173, , http://192.168.18.9:5173///, http://localhost:5173 ',
        );

        $this->assertSame([
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://192.168.18.9:5173',
        ], $origins);
    }

    public function test_wildcards_are_rejected(): void
    {
        $this->assertSame(
            ['http://localhost:5173'],
            FrontendOrigins::parse('*,http://*.example.test,http://localhost:5173'),
        );
    }

    public function test_empty_configuration_falls_back_deterministically(): void
    {
        $this->assertSame(
            ['https://wedding.example'],
            FrontendOrigins::resolve(' , ', 'https://wedding.example/', true),
        );
        $this->assertSame(
            ['http://localhost:5173'],
            FrontendOrigins::resolve(null, null, true),
        );
        $this->assertSame([], FrontendOrigins::resolve(null, null, false));
    }

    public function test_cors_remains_explicit_and_credentialed(): void
    {
        $this->assertTrue(config('cors.supports_credentials'));
        $this->assertSame([], config('cors.allowed_origins_patterns'));
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertSame(['api/*', 'sanctum/csrf-cookie'], config('cors.paths'));
    }

    public function test_canonical_frontend_url_still_builds_invitation_links(): void
    {
        config(['invitations.frontend_url' => 'https://wedding.example/']);

        $this->assertSame(
            'https://wedding.example/invite/public-token',
            (new InvitationAccessToken)->url('public-token'),
        );
    }
}
