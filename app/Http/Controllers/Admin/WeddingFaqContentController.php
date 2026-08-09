<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeddingFaqEntryRequest;
use App\Http\Resources\Admin\WeddingFaqEntryResource;
use App\Models\Wedding;
use App\Services\WeddingContentMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WeddingFaqContentController extends Controller
{
    public function index(): AnonymousResourceCollection|JsonResponse
    {
        $w = Wedding::currentSingleWedding();

        return $w ? WeddingFaqEntryResource::collection($w->faqEntries()->orderBy('sort_order')->orderBy('id')->get()) : $this->missing();
    }

    public function store(WeddingFaqEntryRequest $r, WeddingContentMapper $m)
    {
        $w = Wedding::currentSingleWedding();
        if (! $w) {
            return $this->missing();
        }

return (new WeddingFaqEntryResource($w->faqEntries()->create($m->faq($r->validated()))))->response()->setStatusCode(201);
    }

    public function show(int $faqEntry)
    {
        $x = $this->find($faqEntry);

        return $x ? new WeddingFaqEntryResource($x) : $this->missing();
    }

    public function update(WeddingFaqEntryRequest $r, WeddingContentMapper $m, int $faqEntry)
    {
        $x = $this->find($faqEntry);
        if (! $x) {
            return $this->missing();
        }$x->update($m->faq($r->validated()));

        return new WeddingFaqEntryResource($x->refresh());
    }

    public function destroy(int $faqEntry)
    {
        $x = $this->find($faqEntry);
        if (! $x) {
            return $this->missing();
        }$x->delete();

        return response()->json(status: 204);
    }

    private function find(int $id)
    {
        return Wedding::currentSingleWedding()?->faqEntries()->whereKey($id)->first();
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Wedding content not found.'], 404);
    }
}
