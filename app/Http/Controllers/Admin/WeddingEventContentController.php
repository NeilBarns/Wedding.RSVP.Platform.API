<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeddingEventRequest;
use App\Http\Resources\Admin\WeddingEventResource;
use App\Models\Wedding;
use App\Services\WeddingContentMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WeddingEventContentController extends Controller
{
    public function index(): AnonymousResourceCollection|JsonResponse
    {
        $w = Wedding::currentSingleWedding();

        return $w ? WeddingEventResource::collection($w->events()->orderBy('sort_order')->orderBy('id')->get()) : $this->missing();
    }

    public function store(WeddingEventRequest $r, WeddingContentMapper $m)
    {
        $w = Wedding::currentSingleWedding();
        if (! $w) {
            return $this->missing();
        }

return (new WeddingEventResource($w->events()->create($m->event($r->validated()))))->response()->setStatusCode(201);
    }

    public function show(int $event)
    {
        $x = $this->find($event);

        return $x ? new WeddingEventResource($x) : $this->missing();
    }

    public function update(WeddingEventRequest $r, WeddingContentMapper $m, int $event)
    {
        $x = $this->find($event);
        if (! $x) {
            return $this->missing();
        }$x->update($m->event($r->validated()));

        return new WeddingEventResource($x->refresh());
    }

    public function destroy(int $event)
    {
        $x = $this->find($event);
        if (! $x) {
            return $this->missing();
        }$x->delete();

        return response()->json(status: 204);
    }

    private function find(int $id)
    {
        return Wedding::currentSingleWedding()?->events()->whereKey($id)->first();
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Wedding content not found.'], 404);
    }
}
