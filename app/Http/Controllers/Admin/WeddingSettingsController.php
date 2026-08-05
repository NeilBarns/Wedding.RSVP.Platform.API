<?php

namespace App\Http\Controllers\Admin;

use App\Actions\UpdateWeddingSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWeddingSettingsRequest;
use App\Http\Resources\WeddingResource;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;

class WeddingSettingsController extends Controller
{
    public function show(): WeddingResource|JsonResponse
    {
        $wedding = Wedding::currentSingleWedding();

        if ($wedding === null) {
            return $this->notFound();
        }

        return new WeddingResource($wedding);
    }

    public function update(
        UpdateWeddingSettingsRequest $request,
        UpdateWeddingSettings $updateWeddingSettings,
    ): WeddingResource|JsonResponse {
        $wedding = Wedding::currentSingleWedding();

        if ($wedding === null) {
            return $this->notFound();
        }

        return new WeddingResource(
            $updateWeddingSettings->handle($wedding, $request->validated())
        );
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'message' => 'Wedding not found.',
        ], 404);
    }
}
