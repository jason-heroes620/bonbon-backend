<?php

namespace App\Http\Controllers;

use App\Models\Categories;
use App\Models\EventCategories;
use App\Models\Events;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventCategoriesController extends Controller
{
    public function index()
    {
        return Inertia::render('event-categories/event-categories');
    }

    public function showAll(Request $request)
    {
        $query = EventCategories::query()
            ->join('events', 'events.event_id', '=', 'event_categories.event_id')
            ->join('categories', 'categories.category_id', '=', 'event_categories.category_id')
            ->select([
                'event_categories.event_category_id',
                'event_categories.event_id',
                'event_categories.category_id',
                'events.event_name',
                'categories.category_name',
                'event_categories.created_at',
            ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('events.event_name', 'like', "%{$search}%")
                    ->orWhere('categories.category_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('filters')) {
            foreach ($request->filters as $column => $value) {
                if ($value !== null && $value !== '') {
                    $query->where($column, $value);
                }
            }
        }

        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderBy('event_categories.created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $items = $query->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        $events = Events::query()
            ->select('event_id as value', 'event_name as label')
            ->orderBy('event_name', 'asc')
            ->get();

        $categories = Categories::query()
            ->select('category_id as value', 'category_name as label')
            ->where('is_active', true)
            ->orderBy('category_name', 'asc')
            ->get();

        return Inertia::render('event-categories/create', [
            'events' => $events,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'uuid', 'exists:events,event_id'],
            'category_id' => ['required', 'uuid', 'exists:categories,category_id'],
        ]);

        $exists = EventCategories::query()
            ->where('event_id', $validated['event_id'])
            ->where('category_id', $validated['category_id'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'category_id' => 'This event category mapping already exists.',
            ]);
        }

        EventCategories::create($validated);

        return redirect()->route('event_categories.index')->with([
            'success' => 'Event category created successfully',
        ]);
    }

    public function edit(EventCategories $eventCategory)
    {
        $events = Events::query()
            ->select('event_id as value', 'event_name as label')
            ->orderBy('event_name', 'asc')
            ->get();

        $categories = Categories::query()
            ->select('category_id as value', 'category_name as label')
            ->where('is_active', true)
            ->orderBy('category_name', 'asc')
            ->get();

        return Inertia::render('event-categories/edit', [
            'eventCategory' => $eventCategory,
            'events' => $events,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, EventCategories $eventCategory)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'uuid', 'exists:events,event_id'],
            'category_id' => ['required', 'uuid', 'exists:categories,category_id'],
        ]);

        $exists = EventCategories::query()
            ->where('event_id', $validated['event_id'])
            ->where('category_id', $validated['category_id'])
            ->where('event_category_id', '!=', $eventCategory->event_category_id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'category_id' => 'This event category mapping already exists.',
            ]);
        }

        $eventCategory->update($validated);

        return redirect()->route('event_categories.index')->with([
            'success' => 'Event category updated successfully',
        ]);
    }

    public function destroy(EventCategories $eventCategory)
    {
        $eventCategory->delete();

        return redirect()->route('event_categories.index')->with([
            'success' => 'Event category deleted successfully',
        ]);
    }
}
