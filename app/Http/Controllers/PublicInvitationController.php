<?php

namespace App\Http\Controllers;

use App\Actions\ResolvePublicInvitation;
use App\Http\Resources\PublicInvitationResource;
use Illuminate\Http\JsonResponse;

class PublicInvitationController extends Controller
{
    public function show(
        ResolvePublicInvitation $resolveInvitation,
        string $token,
    ): PublicInvitationResource|JsonResponse {
        $invitation = $resolveInvitation->handle($token);

        if ($invitation === null) {
            return response()->json([
                'message' => 'Invitation not found.',
            ], 404);
        }

        return new PublicInvitationResource($invitation);
    }
}
