<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGalleryImage;
use App\Models\GalleryImages;
use App\Models\GalleryImageUsers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;

class GalleryImagesController extends Controller
{
    public function index()
    {
        return Inertia::render('gallery/gallery');
    }

    public function showAll(Request $request)
    {
        $query = GalleryImages::query()->withCount('taggedUsers');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('caption', 'like', "%{$search}%");
            });
        }

        if ($request->has('filters')) {
            foreach ($request->filters as $column => $value) {
                if ($value !== null) {
                    $query->where($column, $value);
                }
            }
        }

        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderByDesc('created_at');
        }

        $perPage = $request->per_page ?? 10;
        $images = $query->paginate($perPage);

        return response()->json([
            'data' => $images->items(),
            'meta' => [
                'current_page' => $images->currentPage(),
                'last_page' => $images->lastPage(),
                'per_page' => $images->perPage(),
                'total' => $images->total(),
                'from' => $images->firstItem(),
                'to' => $images->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'title' => ['nullable', 'string', 'max:150'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'emails' => ['nullable', 'array'],
            'emails.*' => ['email'],
        ])->validate();

        $title = $validated['title'] ?? null;
        $caption = $validated['caption'] ?? null;
        $emails = $this->normalizeEmails($validated['emails'] ?? []);
        $userIds = $this->resolveUserIds($emails);

        $created = 0;

        foreach ($request->file('images') as $file) {
            $id = (string) Str::uuid();
            $uuid = (string) Str::uuid();
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
            $originalPath = "gallery/{$id}/original/{$uuid}.{$extension}";

            Storage::disk('public')->putFileAs(
                "gallery/{$id}/original",
                $file,
                "{$uuid}.{$extension}",
            );

            [$width, $height] = array_pad((array) getimagesize($file->getRealPath()), 2, null);

            $image = GalleryImages::create([
                'gallery_image_id' => $id,
                'title' => $title,
                'caption' => $caption,
                'original_path' => $originalPath,
                'original_url' => Storage::url($originalPath),
                'mime_type' => $file->getMimeType(),
                'image_width' => $width ? (int) $width : null,
                'image_height' => $height ? (int) $height : null,
                'file_size_bytes' => $file->getSize(),
                'status' => 'pending',
                'is_active' => true,
                'uploaded_by' => Auth::id(),
            ]);

            $this->dispatchProcessing($image->gallery_image_id);

            if (!empty($emails)) {
                $this->tagImage($image, $emails, $userIds);
            }

            $created++;
        }

        return redirect()->back()->with('success', "{$created} image(s) uploaded.");
    }

    public function show(GalleryImages $galleryImage)
    {
        return Inertia::render('gallery/show', [
            'image' => $galleryImage->load(['taggedUsers.user']),
        ]);
    }

    public function update(GalleryImages $galleryImage, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'title' => ['nullable', 'string', 'max:150'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ])->validate();

        $galleryImage->update([
            'title' => $validated['title'] ?? null,
            'caption' => $validated['caption'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()->back()->with('success', 'Image updated.');
    }

    public function destroy(GalleryImages $galleryImage)
    {
        $paths = array_filter([
            $galleryImage->original_path,
            $galleryImage->thumbnail_path,
            $galleryImage->optimized_path,
        ]);

        if (!empty($paths)) {
            Storage::disk('public')->delete($paths);
        }

        $galleryImage->taggedUsers()->delete();
        $galleryImage->delete();

        return redirect()->back()->with('success', 'Image deleted.');
    }

    public function tagUsers(GalleryImages $galleryImage, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'emails' => ['required', 'array', 'min:1'],
            'emails.*' => ['email'],
        ])->validate();

        $emails = $this->normalizeEmails($validated['emails']);
        $userIds = $this->resolveUserIds($emails);
        $this->tagImage($galleryImage, $emails, $userIds);

        return redirect()->back()->with('success', 'Users tagged.');
    }

    public function untagUser(GalleryImages $galleryImage, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ])->validate();

        $email = strtolower(trim($validated['email']));

        GalleryImageUsers::query()
            ->where('gallery_image_id', $galleryImage->gallery_image_id)
            ->where('email', $email)
            ->delete();

        return redirect()->back()->with('success', 'User untagged.');
    }

    public function bulkTag(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'gallery_image_ids' => ['required', 'array', 'min:1'],
            'gallery_image_ids.*' => ['uuid', 'exists:gallery_images,gallery_image_id'],
            'emails' => ['required', 'array', 'min:1'],
            'emails.*' => ['email'],
        ])->validate();

        $emails = $this->normalizeEmails($validated['emails']);
        $userIds = $this->resolveUserIds($emails);
        $now = now();
        $rows = [];

        foreach ($validated['gallery_image_ids'] as $imageId) {
            foreach ($emails as $email) {
                $rows[] = [
                    'gallery_image_user_id' => (string) Str::uuid(),
                    'gallery_image_id' => $imageId,
                    'user_id' => $userIds[$email] ?? null,
                    'email' => $email,
                    'tagged_by' => Auth::id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($rows)) {
            GalleryImageUsers::insertOrIgnore($rows);
        }

        return redirect()->back()->with('success', 'Images tagged.');
    }

    private function dispatchProcessing(string $galleryImageId): void
    {
        if (config('gallery.async_processing')) {
            ProcessGalleryImage::dispatch($galleryImageId);
            return;
        }

        ProcessGalleryImage::dispatchSync($galleryImageId);
    }

    /**
     * @param  array<int, string>  $emails
     * @return array<string, string>
     */
    private function resolveUserIds(array $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        return User::query()
            ->whereIn('email', $emails)
            ->pluck('user_id', 'email')
            ->all();
    }

    /**
     * @param  array<int, string>  $emails
     * @param  array<string, string>  $userIds
     */
    private function tagImage(GalleryImages $image, array $emails, array $userIds): void
    {
        foreach ($emails as $email) {
            GalleryImageUsers::firstOrCreate(
                [
                    'gallery_image_id' => $image->gallery_image_id,
                    'email' => $email,
                ],
                [
                    'user_id' => $userIds[$email] ?? null,
                    'tagged_by' => Auth::id(),
                ],
            );
        }
    }

    /**
     * @param  array<int, mixed>  $emails
     * @return array<int, string>
     */
    private function normalizeEmails(array $emails): array
    {
        return collect($emails)
            ->filter(fn ($email) => is_string($email) && $email !== '')
            ->map(fn ($email) => strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();
    }
}
