<?php

namespace App\Http\Controllers;

use App\Actions\SubmitPublicRsvp;
use App\Exceptions\RsvpUnavailableException;
use App\Http\Requests\SubmitPublicRsvpRequest;
use App\Http\Resources\PublicRsvpConfirmationResource;
use Illuminate\Http\JsonResponse;

class PublicRsvpController extends Controller
{
    public function update(
        SubmitPublicRsvpRequest $request,
        SubmitPublicRsvp $submitRsvp,
        string $token,
    ): PublicRsvpConfirmationResource|JsonResponse {
        try {
            $result = $submitRsvp->handle($token, $request->validated());
        } catch (RsvpUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        if ($result === null) {
            return response()->json(['message' => 'Invitation not found.'], 404);
        }

        [$invitation, $revision] = $result;

        return new PublicRsvpConfirmationResource([
            'invitation' => $invitation,
            'revision' => $revision,
        ]);
    }
}
