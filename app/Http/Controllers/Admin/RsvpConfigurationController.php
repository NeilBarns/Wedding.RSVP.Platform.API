<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRsvpConfigurationRequest;
use App\Models\Wedding;
use App\Services\RsvpConfigurationService;
use Illuminate\Http\JsonResponse;

class RsvpConfigurationController extends Controller
{
    public function show(RsvpConfigurationService $configuration): JsonResponse
    {
        $wedding = Wedding::currentSingleWedding();

        if ($wedding === null) {
            return $this->notFound();
        }

        return response()->json(['data' => ['questions' => $configuration->questions($wedding)]]);
    }

    public function update(
        UpdateRsvpConfigurationRequest $request,
        RsvpConfigurationService $configuration,
    ): JsonResponse {
        $wedding = Wedding::currentSingleWedding();

        if ($wedding === null) {
            return $this->notFound();
        }

        $configuration->replace($wedding, $request->validated('questions'));

        return response()->json(['data' => ['questions' => $configuration->questions($wedding)]]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Wedding not found.'], 404);
    }
}
