<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GalleryImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = (int) $request->query('per_page', 20);

        $images = $this->userImagesQuery($user)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $items = collect($images->items())->map(function (GalleryImages $image) {
            return [
                'gallery_image_id' => $image->gallery_image_id,
                'thumbnail_url' => $image->thumbnail_url ?? $image->original_url,
                'optimized_url' => $image->optimized_url ?? $image->original_url,
                'caption' => $image->caption,
                'image_width' => $image->image_width,
                'image_height' => $image->image_height,
                'created_at' => $image->created_at,
            ];
        })->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $images->currentPage(),
                'last_page' => $images->lastPage(),
                'per_page' => $images->perPage(),
                'total' => $images->total(),
                'from' => $images->firstItem(),
                'to' => $images->lastItem(),
                'next_page_url' => $images->nextPageUrl(),
                'prev_page_url' => $images->previousPageUrl(),
            ],
        ]);
    }

    public function download(Request $request, string $gallery_image_id)
    {
        $user = $request->user();

        $image = $this->userImagesQuery($user)
            ->where('gallery_image_id', $gallery_image_id)
            ->first();

        if (!$image) {
            return response()->json(['message' => 'Image not found.'], 404);
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($image->original_path)) {
            return response()->json(['message' => 'Image file is missing.'], 404);
        }

        $base = $image->title ? Str::slug($image->title) : 'bonbon-gallery';
        $filename = $base . '-' . basename($image->original_path);

        return $disk->download($image->original_path, $filename);
    }

    private function userImagesQuery($user)
    {
        return GalleryImages::query()
            ->where('is_active', true)
            ->where('status', 'ready')
            ->whereHas('taggedUsers', function ($query) use ($user) {
                $query->where('user_id', $user->user_id)
                    ->orWhere('email', $user->email);
            });
    }
}
