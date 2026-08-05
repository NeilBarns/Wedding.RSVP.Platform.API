<?php

namespace App\Http\Controllers;

use App\Http\Resources\WeddingResource;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;

class WeddingController extends Controller
{
    public function show(): WeddingResource|JsonResponse
    {
        $wedding = Wedding::currentSingleWedding();

        if ($wedding === null) {
            return response()->json([
                'message' => 'Wedding not found.',
            ], 404);
        }

        return new WeddingResource($wedding);
    }
}
