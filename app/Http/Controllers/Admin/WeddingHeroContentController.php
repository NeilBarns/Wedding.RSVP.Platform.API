<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeddingHeroContentRequest;
use App\Http\Resources\Admin\WeddingHeroContentResource;
use App\Models\Wedding;
use App\Services\WeddingContentMapper;
use Illuminate\Http\JsonResponse;

class WeddingHeroContentController extends Controller
{
    public function show(): WeddingHeroContentResource|JsonResponse
    {
        $w = Wedding::currentSingleWedding();
        if (! $w) {
            return $this->missing();
        } $hero = $w->heroContent;

        return $hero ? new WeddingHeroContentResource($hero) : $this->missing();
    }

    public function update(WeddingHeroContentRequest $r, WeddingContentMapper $m): WeddingHeroContentResource|JsonResponse
    {
        $w = Wedding::currentSingleWedding();
        if (! $w) {
            return $this->missing();
        } $hero = $w->heroContent()->updateOrCreate([], $m->hero($r->validated()));

        return new WeddingHeroContentResource($hero);
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Wedding content not found.'], 404);
    }
}
