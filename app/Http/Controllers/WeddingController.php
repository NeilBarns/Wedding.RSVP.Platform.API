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

        $published = $wedding->status === Wedding::STATUS_PUBLISHED;
        $wedding->load([
            'heroContent' => fn ($query) => $query->where('is_published', $published),
            'storyEntries' => fn ($query) => $query->where('is_published', $published)->when(! $published, fn ($query) => $query->whereRaw('1 = 0'))->orderBy('sort_order')->orderBy('id'),
            'events' => fn ($query) => $query->where('is_published', $published)->when(! $published, fn ($query) => $query->whereRaw('1 = 0'))->orderBy('sort_order')->orderBy('id'),
            'faqEntries' => fn ($query) => $query->where('is_published', $published)->when(! $published, fn ($query) => $query->whereRaw('1 = 0'))->orderBy('sort_order')->orderBy('id'),
            'galleryEntries' => fn ($query) => $query->where('is_published', $published)->when(! $published, fn ($query) => $query->whereRaw('1 = 0'))->orderBy('sort_order')->orderBy('id'),
        ]);

        if (! $published) {
            $wedding->setRelation('heroContent', null);
        }

        return new WeddingResource($wedding);
    }
}
