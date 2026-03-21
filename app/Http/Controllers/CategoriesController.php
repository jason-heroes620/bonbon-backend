<?php

namespace App\Http\Controllers;

use App\Models\Categories;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoriesController extends Controller
{
    public function index()
    {
        return Inertia::render('categories/categories');
    }

    public function showAll(Request $request)
    {
        $query = Categories::query()->with(['parent:category_id,category_name']);

        if ($search = $request->input('search')) {
            $query->where('category_name', 'like', "%{$search}%");
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
            $query->orderBy('categories.created_at', 'desc');
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

    public function create()
    {
        $parentCategories = Categories::select('category_id as value', 'category_name as label')
            ->where('is_active', true)
            ->orderBy('category_name', 'asc')
            ->get();

        return Inertia::render('categories/create', [
            'parentCategories' => $parentCategories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'uuid', 'exists:categories,category_id'],
            'is_active' => ['required', 'boolean'],
        ]);

        Categories::create($validated);

        return redirect()->route('categories.index')->with([
            'success' => 'Category created successfully',
        ]);
    }

    public function edit(Categories $category)
    {
        $parentCategories = Categories::select('category_id as value', 'category_name as label')
            ->where('is_active', true)
            ->where('category_id', '!=', $category->category_id)
            ->orderBy('category_name', 'asc')
            ->get();

        return Inertia::render('categories/edit', [
            'category' => $category,
            'parentCategories' => $parentCategories,
        ]);
    }

    public function update(Request $request, Categories $category)
    {
        $validated = $request->validate([
            'category_name' => ['required', 'string', 'max:150'],
            'parent_id' => [
                'nullable',
                'uuid',
                'exists:categories,category_id',
                Rule::notIn([$category->category_id]),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $category->update($validated);

        return redirect()->route('categories.index')->with([
            'success' => 'Category updated successfully',
        ]);
    }

    public function destroy(Categories $category)
    {
        $category->update([
            'is_active' => false,
        ]);

        return redirect()->route('categories.index')->with([
            'success' => 'Category deleted successfully',
        ]);
    }

    public function getCategoryList()
    {
        $categories = Categories::select('category_id as value', 'category_name as label')
            ->where('is_active', true)
            ->orderBy('category_name', 'asc')
            ->get();

        return response()->json($categories);
    }
}
