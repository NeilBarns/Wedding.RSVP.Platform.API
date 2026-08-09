<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWeddingMediaRequest;
use App\Http\Requests\UpdateWeddingMediaRequest;
use App\Http\Resources\Admin\WeddingMediaResource;
use App\Models\Wedding;
use App\Models\WeddingMedia;
use App\Services\WeddingMediaService;
use Illuminate\Http\JsonResponse;

class WeddingMediaController extends Controller
{
    public function index()
    {
        $wedding = Wedding::currentSingleWedding();
        if (! $wedding) {
            return $this->missing();
        }
        $perPage = min(100, max(1, request()->integer('perPage', 20)));

        return WeddingMediaResource::collection($wedding->media()->latest()->paginate($perPage));
    }

    public function store(StoreWeddingMediaRequest $request, WeddingMediaService $service)
    {
        $wedding = Wedding::currentSingleWedding();
        if (! $wedding) {
            return $this->missing();
        }
        $media = $service->store($wedding, $request->file('file'), $request->validated('altText'));

        return (new WeddingMediaResource($media))->response()->setStatusCode(201);
    }

    public function show(int $media)
    {
        $record = $this->find($media);

        return $record ? new WeddingMediaResource($record) : $this->missing();
    }

    public function update(UpdateWeddingMediaRequest $request, int $media)
    {
        $record = $this->find($media);
        if (! $record) {
            return $this->missing();
        }
        $record->update(['alt_text' => $request->validated('altText')]);

        return new WeddingMediaResource($record->refresh());
    }

    public function destroy(WeddingMediaService $service, int $media)
    {
        $record = $this->find($media);
        if (! $record) {
            return $this->missing();
        }
        try {
            $service->delete($record);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'media_in_use'], 409);
        }

        return response()->json(status: 204);
    }

    private function find(int $id): ?WeddingMedia
    {
        return Wedding::currentSingleWedding()?->media()->whereKey($id)->first();
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Wedding media not found.'], 404);
    }
}
