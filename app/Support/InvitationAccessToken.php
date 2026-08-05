<?php

namespace App\Support;

use App\Models\Invitation;
use RuntimeException;

class InvitationAccessToken
{
    public function generate(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

            if (! Invitation::query()->where('token_hash', $this->hash($token))->exists()) {
                return $token;
            }
        }

        throw new RuntimeException('Unable to generate a unique invitation access token.');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function url(string $token): string
    {
        return rtrim(config('invitations.frontend_url'), '/').'/invite/'.$token;
    }
}
