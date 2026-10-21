<?php

namespace App\Jobs;

use App\Models\GalleryImages;
use App\Services\ImageOptimizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessGalleryImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $galleryImageId)
    {
    }

    public function handle(ImageOptimizer $optimizer): void
    {
        $image = GalleryImages::find($this->galleryImageId);
        if (!$image) {
            return;
        }

        $image->update(['status' => 'processing', 'failure_reason' => null]);

        try {
            $disk = Storage::disk('public');
            $absolutePath = $disk->path($image->original_path);
            $baseName = pathinfo($image->original_path, PATHINFO_FILENAME);

            $thumbnail = $optimizer->optimize(
                $absolutePath,
                (int) config('gallery.thumbnail_max_width', 400),
                (int) config('gallery.thumbnail_max_bytes', 81920),
            );
            $thumbnailPath = "gallery/{$image->gallery_image_id}/thumbnail/{$baseName}.{$thumbnail['extension']}";
            $disk->put($thumbnailPath, $thumbnail['binary']);

            $optimized = $optimizer->optimize(
                $absolutePath,
                (int) config('gallery.optimized_max_width', 1600),
                (int) config('gallery.optimized_max_bytes', 512000),
            );
            $optimizedPath = "gallery/{$image->gallery_image_id}/optimized/{$baseName}.{$optimized['extension']}";
            $disk->put($optimizedPath, $optimized['binary']);

            $image->update([
                'thumbnail_path' => $thumbnailPath,
                'thumbnail_url' => $disk->url($thumbnailPath),
                'optimized_path' => $optimizedPath,
                'optimized_url' => $disk->url($optimizedPath),
                'status' => 'ready',
                'failure_reason' => null,
            ]);
        } catch (\Throwable $e) {
            $image->update([
                'status' => 'failed',
                'failure_reason' => Str::limit($e->getMessage(), 250),
            ]);

            throw $e;
        }
    }
}
