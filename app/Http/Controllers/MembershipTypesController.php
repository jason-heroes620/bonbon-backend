<?php

namespace App\Http\Controllers;

use App\Models\MembershipTypes;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MembershipTypesController extends Controller
{
    public function index()
    {
        return Inertia::render('membership-types/membership-types');
    }

    public function showAll(Request $request)
    {
        $query = MembershipTypes::query();

        if ($search = $request->input('search')) {
            $query->where('membership_type', 'like', "%{$search}%");
        }

        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderBy('membership_types.created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $types = $query->paginate($perPage);

        return response()->json([
            'data' => $types->items(),
            'meta' => [
                'current_page' => $types->currentPage(),
                'last_page' => $types->lastPage(),
                'per_page' => $types->perPage(),
                'total' => $types->total(),
                'from' => $types->firstItem(),
                'to' => $types->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('membership-types/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'membership_type' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        MembershipTypes::create($validated);

        return redirect()->route('membership_types.index')->with([
            'success' => 'Membership type created successfully',
        ]);
    }

    public function edit(MembershipTypes $membershipType)
    {
        return Inertia::render('membership-types/edit', [
            'membershipType' => $membershipType,
        ]);
    }

    public function update(Request $request, MembershipTypes $membershipType)
    {
        $validated = $request->validate([
            'membership_type' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $membershipType->update($validated);

        return redirect()->route('membership_types.index')->with([
            'success' => 'Membership type updated successfully',
        ]);
    }
}
