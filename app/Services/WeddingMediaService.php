<?php

namespace App\Services;

use App\Models\Wedding;
use App\Models\WeddingMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WeddingMediaService
{
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function store(Wedding $wedding, UploadedFile $file, ?string $altText): WeddingMedia
    {
        $mime = $file->getMimeType();
        $extension = self::EXTENSIONS[$mime] ?? null;
        $dimensions = @getimagesize($file->getRealPath());
        if (! $extension || ! $dimensions) {
            throw new RuntimeException('The uploaded image could not be inspected safely.');
        }

        $disk = (string) config('filesystems.wedding_media_disk');
        $directory = "weddings/{$wedding->id}/media";
        $path = $directory.'/'.Str::uuid().'.'.$extension;
        if (! Storage::disk($disk)->putFileAs($directory, $file, basename($path), ['visibility' => 'public'])) {
            throw new RuntimeException('The uploaded image could not be stored.');
        }

        try {
            return $wedding->media()->create([
                'disk' => $disk, 'path' => $path, 'original_file_name' => $file->getClientOriginalName(),
                'mime_type' => $mime, 'file_size_bytes' => $file->getSize(), 'width' => $dimensions[0],
                'height' => $dimensions[1], 'alt_text' => $altText,
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    public function delete(WeddingMedia $media): void
    {
        if ($media->isReferenced()) {
            throw new RuntimeException('This image is currently used by wedding content and cannot be deleted.');
        }
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
    }
}
