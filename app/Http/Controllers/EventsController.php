<?php

namespace App\Http\Controllers;

use App\Events\ImageUpdated;
use App\Models\EventImages;
use App\Models\EventCategories;
use App\Models\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EventsController extends Controller
{
    public function index()
    {
        return Inertia::render('events/events');
    }

    public function showAll(Request $request)
    {
        $query = Events::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('event_name', 'like', "%{$search}%")
                    ->orWhere('event_location', 'like', "%{$search}%")
                    ->orWhere('location_name', 'like', "%{$search}%");
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
            $query->orderBy('events.created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $events = $query->paginate($perPage);

        return response()->json([
            'data' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'from' => $events->firstItem(),
                'to' => $events->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('events/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:100'],
            'event_start_date' => ['required', 'date'],
            'event_end_date' => ['required', 'date', 'after_or_equal:event_start_date'],
            'event_start_time' => ['required'],
            'event_end_time' => ['required'],
            'event_location' => ['required', 'string', 'max:100'],
            'event_description' => ['required', 'string'],
            'location_name' => ['required', 'string', 'max:150'],
            'location_latitude' => ['required', 'numeric'],
            'location_longitude' => ['required', 'numeric'],
            'place_id' => ['required', 'string', 'max:255'],
            'require_registration' => ['required', 'boolean'],
            'registration_type' => ['required', 'in:free,paid'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'is_unlimited_seats' => ['required', 'boolean'],
            'seat_limit' => ['nullable', 'integer', 'min:1'],
            'seat_hold_minutes' => ['required', 'integer', 'min:1'],
            'rsvp_open_at' => ['nullable', 'date'],
            'rsvp_close_at' => ['nullable', 'date'],
            'require_questionnaire' => ['required', 'boolean'],
            'is_published' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['uuid', 'exists:ev_categories,category_id'],
            'event_image' => ['nullable', 'image', 'max:4096'],
            'event_images' => ['nullable', 'array'],
            'event_images.*' => ['image', 'max:4096'],
        ]);

        $validated = $this->normalizeCommerceFields($validated);
        $this->validateCommerceFields($validated);

        $event = Events::create($validated);

        $categoryIds = $request->input('categories');
        if (is_array($categoryIds) && !empty($categoryIds)) {
            foreach ($categoryIds as $categoryId) {
                EventCategories::create([
                    'event_id' => $event->event_id,
                    'category_id' => $categoryId,
                ]);
            }
        }

        if ($request->hasFile('event_image')) {
            $path = $request->file('event_image')->store("events/{$event->event_id}", 'public');
            $event->update([
                'event_image_path' => Storage::url($path),
            ]);
        }

        if ($request->hasFile('event_images')) {
            foreach ((array) $request->file('event_images') as $image) {
                if (!$image) {
                    continue;
                }
                $path = $image->store("events/{$event->event_id}/images", 'public');
                EventImages::create([
                    'event_id' => $event->event_id,
                    'event_image_path' => Storage::url($path),
                    'is_enabled' => true,
                ]);
            }
        }

        return redirect()->route('events.index')->with([
            'success' => 'Event created successfully',
        ]);
    }

    public function edit(Events $event)
    {
        $eventImages = EventImages::query()
            ->where('event_id', $event->event_id)
            ->orderBy('created_at', 'desc')
            ->get(['event_image_id', 'event_image_path', 'is_enabled']);

        $event->setAttribute('event_images', $eventImages);
        $event->setAttribute(
            'categories',
            EventCategories::query()->where('event_id', $event->event_id)
                ->pluck('category_id')
                ->toArray(),
        );

        return Inertia::render('events/edit', [
            'event' => $event,
        ]);
    }

    public function update(Request $request, Events $event)
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:100'],
            'event_start_date' => ['required', 'date'],
            'event_end_date' => ['required', 'date', 'after_or_equal:event_start_date'],
            'event_start_time' => ['required'],
            'event_end_time' => ['required'],
            'event_location' => ['required', 'string', 'max:100'],
            'event_description' => ['required', 'string'],
            'location_name' => ['required', 'string', 'max:150'],
            'location_latitude' => ['required', 'numeric'],
            'location_longitude' => ['required', 'numeric'],
            'place_id' => ['required', 'string', 'max:255'],
            'require_registration' => ['required', 'boolean'],
            'registration_type' => ['required', 'in:free,paid'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'is_unlimited_seats' => ['required', 'boolean'],
            'seat_limit' => ['nullable', 'integer', 'min:1'],
            'seat_hold_minutes' => ['required', 'integer', 'min:1'],
            'rsvp_open_at' => ['nullable', 'date'],
            'rsvp_close_at' => ['nullable', 'date'],
            'require_questionnaire' => ['required', 'boolean'],
            'is_published' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['uuid', 'exists:ev_categories,category_id'],
            'event_image' => ['nullable', 'image', 'max:4096'],
            'event_images' => ['nullable', 'array'],
            'event_images.*' => ['image', 'max:4096'],
            'delete_event_image_ids' => ['nullable', 'array'],
            'delete_event_image_ids.*' => ['integer'],
            'disabled_event_image_ids' => ['nullable', 'array'],
            'disabled_event_image_ids.*' => ['integer'],
            'disabled_event_image_ids_present' => ['nullable', 'boolean'],
        ]);

        $validated = $this->normalizeCommerceFields($validated);
        $this->validateCommerceFields($validated);

        $event->update($validated);

        $categoryIds = $request->input('categories');
        if (is_array($categoryIds)) {
            EventCategories::query()->where('event_id', $event->event_id)->delete();
            foreach ($categoryIds as $categoryId) {
                EventCategories::create([
                    'event_id' => $event->event_id,
                    'category_id' => $categoryId,
                ]);
            }
        }

        if ($request->hasFile('event_image')) {
            if (!empty($event->event_image_path)) {
                $this->deletePublicUrlFile($event->event_image_path);
            }

            $path = $request->file('event_image')->store("events/{$event->event_id}", 'public');
            $event->update([
                'event_image_path' => Storage::url($path),
            ]);
        }

        $deleteIds = $request->input('delete_event_image_ids');
        if (is_array($deleteIds) && !empty($deleteIds)) {
            $imagesToDelete = EventImages::query()
                ->where('event_id', $event->event_id)
                ->whereIn('event_image_id', $deleteIds)
                ->get();

            foreach ($imagesToDelete as $image) {
                $this->deletePublicUrlFile($image->event_image_path);
                $image->delete();
            }
        }

        if ($request->boolean('disabled_event_image_ids_present')) {
            $disabledIds = $request->input('disabled_event_image_ids');
            $disabledIds = is_array($disabledIds) ? $disabledIds : [];

            EventImages::query()
                ->where('event_id', $event->event_id)
                ->update(['is_enabled' => true]);

            if ($disabledIds !== []) {
                EventImages::query()
                    ->where('event_id', $event->event_id)
                    ->whereIn('event_image_id', $disabledIds)
                    ->update(['is_enabled' => false]);

                event(new ImageUpdated([
                    'event_id' => $event->event_id,
                    'disabled_event_image_ids' => $disabledIds,
                ]));
            }
        }

        if ($request->hasFile('event_images')) {
            foreach ((array) $request->file('event_images') as $image) {
                if (!$image) {
                    continue;
                }
                $path = $image->store("events/{$event->event_id}/images", 'public');
                EventImages::create([
                    'event_id' => $event->event_id,
                    'event_image_path' => Storage::url($path),
                    'is_enabled' => true,
                ]);
            }
        }

        return redirect()->back()->with([
            'success' => 'Event updated successfully',
        ]);
    }

    private function deletePublicUrlFile(?string $url): void
    {
        if (!$url) {
            return;
        }
        $relative = ltrim(str_replace('/storage/', '', $url), '/');
        if ($relative !== $url) {
            Storage::disk('public')->delete($relative);
        }
    }

    private function validateCommerceFields(array $validated): void
    {
        if (!(bool) ($validated['require_registration'] ?? false)) {
            return;
        }

        $registrationType = (string) ($validated['registration_type'] ?? 'free');
        $basePrice = (float) ($validated['base_price'] ?? 0);
        $unlimitedSeats = (bool) ($validated['is_unlimited_seats'] ?? true);
        $seatLimit = $validated['seat_limit'] ?? null;
        $rsvpOpenAt = $validated['rsvp_open_at'] ?? null;
        $rsvpCloseAt = $validated['rsvp_close_at'] ?? null;

        if ($registrationType === 'paid' && $basePrice <= 0) {
            throw ValidationException::withMessages([
                'base_price' => 'Base price must be greater than 0 for paid events.',
            ]);
        }

        if ($registrationType === 'free' && $basePrice != 0.0) {
            throw ValidationException::withMessages([
                'base_price' => 'Base price must be 0 for free events.',
            ]);
        }

        if (!$unlimitedSeats) {
            if ($seatLimit === null || (int) $seatLimit <= 0) {
                throw ValidationException::withMessages([
                    'seat_limit' => 'Seat limit is required when unlimited seats is off.',
                ]);
            }
        }

        if ($unlimitedSeats && $seatLimit !== null) {
            throw ValidationException::withMessages([
                'seat_limit' => 'Seat limit must be empty when unlimited seats is on.',
            ]);
        }

        if ($rsvpOpenAt !== null && $rsvpCloseAt !== null && strtotime((string) $rsvpOpenAt) >= strtotime((string) $rsvpCloseAt)) {
            throw ValidationException::withMessages([
                'rsvp_close_at' => 'RSVP close time must be after RSVP open time.',
            ]);
        }
    }

    private function normalizeCommerceFields(array $validated): array
    {
        if ((bool) ($validated['require_registration'] ?? false)) {
            return $validated;
        }

        $validated['registration_type'] = 'free';
        $validated['base_price'] = 0;
        $validated['is_unlimited_seats'] = true;
        $validated['seat_limit'] = null;
        $validated['seat_hold_minutes'] = 15;
        $validated['rsvp_open_at'] = null;
        $validated['rsvp_close_at'] = null;
        $validated['require_questionnaire'] = false;

        return $validated;
    }
}
