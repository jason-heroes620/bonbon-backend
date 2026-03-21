<?php

namespace App\Http\Controllers;

use App\Models\EvCategories;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EvCategoriesController extends Controller
{
    public function index()
    {
        return Inertia::render('event-categories/event-categories');
    }

    public function showAll(Request $request)
    {
        $query = EvCategories::query();

        if ($search = $request->input('search')) {
            $query->where('category_name', 'like', "%{$search}%");
        }

        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderBy('ev_categories.created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $categories = $query->paginate($perPage);

        return response()->json([
            'data' => $categories->items(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'from' => $categories->firstItem(),
                'to' => $categories->lastItem(),
            ],
        ]);
    }

    public function getEvCategoryList()
    {
        $options = EvCategories::query()
            ->where('is_active', true)
            ->orderBy('category_name')
            ->get(['category_id', 'category_name'])
            ->map(fn ($c) => ['value' => $c->category_id, 'label' => $c->category_name])
            ->values();

        return response()->json($options);
    }

    public function create()
    {
        return Inertia::render('event-categories/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_name' => ['required', 'string', 'max:50', Rule::unique('ev_categories', 'category_name')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        EvCategories::create($validated);

        return redirect()->route('ev_categories.index')->with([
            'success' => 'Event category created successfully',
        ]);
    }

    public function edit(EvCategories $evCategory)
    {
        return Inertia::render('event-categories/edit', [
            'evCategory' => $evCategory,
        ]);
    }

    public function update(Request $request, EvCategories $evCategory)
    {
        $validated = $request->validate([
            'category_name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('ev_categories', 'category_name')->ignore($evCategory->category_id, 'category_id'),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $evCategory->update($validated);

        return redirect()->route('ev_categories.index')->with([
            'success' => 'Event category updated successfully',
        ]);
    }

    public function destroy(EvCategories $evCategory)
    {
        $evCategory->delete();

        return redirect()->route('ev_categories.index')->with([
            'success' => 'Event category deleted successfully',
        ]);
    }
}
