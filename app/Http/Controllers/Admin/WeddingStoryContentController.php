<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeddingStoryEntryRequest;
use App\Http\Resources\Admin\WeddingStoryEntryResource;
use App\Models\Wedding;
use App\Services\WeddingContentMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WeddingStoryContentController extends Controller
{
    public function index(): AnonymousResourceCollection|JsonResponse
    {
        $w = Wedding::currentSingleWedding();

        return $w ? WeddingStoryEntryResource::collection($w->storyEntries()->orderBy('sort_order')->orderBy('id')->get()) : $this->missing();
    }

    public function store(WeddingStoryEntryRequest $r, WeddingContentMapper $m)
    {
        $w = Wedding::currentSingleWedding();
        if (! $w) {
            return $this->missing();
        }

return (new WeddingStoryEntryResource($w->storyEntries()->create($m->story($r->validated()))))->response()->setStatusCode(201);
    }

    public function show(int $storyEntry)
    {
        $x = $this->find($storyEntry);

        return $x ? new WeddingStoryEntryResource($x) : $this->missing();
    }

    public function update(WeddingStoryEntryRequest $r, WeddingContentMapper $m, int $storyEntry)
    {
        $x = $this->find($storyEntry);
        if (! $x) {
            return $this->missing();
        }$x->update($m->story($r->validated()));

        return new WeddingStoryEntryResource($x->refresh());
    }

    public function destroy(int $storyEntry)
    {
        $x = $this->find($storyEntry);
        if (! $x) {
            return $this->missing();
        }$x->delete();

        return response()->json(status: 204);
    }

    private function find(int $id)
    {
        return Wedding::currentSingleWedding()?->storyEntries()->whereKey($id)->first();
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Wedding content not found.'], 404);
    }
}
