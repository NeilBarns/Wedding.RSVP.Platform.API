<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeddingGalleryEntryRequest;
use App\Http\Resources\Admin\WeddingGalleryEntryResource;
use App\Models\Wedding;
use App\Services\WeddingContentMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WeddingGalleryContentController extends Controller
{
    public function index(): AnonymousResourceCollection|JsonResponse
    {
        $w = Wedding::currentSingleWedding();

        return $w ? WeddingGalleryEntryResource::collection($w->galleryEntries()->orderBy('sort_order')->orderBy('id')->get()) : $this->missing();
    }

    public function store(WeddingGalleryEntryRequest $r, WeddingContentMapper $m)
    {
        $w = Wedding::currentSingleWedding();
        if (! $w) {
            return $this->missing();
        }

return (new WeddingGalleryEntryResource($w->galleryEntries()->create($m->gallery($r->validated()))))->response()->setStatusCode(201);
    }

    public function show(int $galleryEntry)
    {
        $x = $this->find($galleryEntry);

        return $x ? new WeddingGalleryEntryResource($x) : $this->missing();
    }

    public function update(WeddingGalleryEntryRequest $r, WeddingContentMapper $m, int $galleryEntry)
    {
        $x = $this->find($galleryEntry);
        if (! $x) {
            return $this->missing();
        }$x->update($m->gallery($r->validated()));

        return new WeddingGalleryEntryResource($x->refresh());
    }

    public function destroy(int $galleryEntry)
    {
        $x = $this->find($galleryEntry);
        if (! $x) {
            return $this->missing();
        }$x->delete();

        return response()->json(status: 204);
    }

    private function find(int $id)
    {
        return Wedding::currentSingleWedding()?->galleryEntries()->whereKey($id)->first();
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Wedding content not found.'], 404);
    }
}
