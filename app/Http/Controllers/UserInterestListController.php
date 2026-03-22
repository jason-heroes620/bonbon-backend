<?php

namespace App\Http\Controllers;

use App\Models\UserInterestList;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserInterestListController extends Controller
{
    public function index()
    {
        return Inertia::render('user-interest-list/user-interest-list');
    }

    public function showAll(Request $request)
    {
        $query = UserInterestList::query();

        if ($search = $request->input('search')) {
            $query->where('email', 'like', "%{$search}%");
        }

        $allowedSortFields = [
            'email',
            'created_at',
        ];

        if ($request->has('sort')) {
            $field = (string) ($request->sort['field'] ?? '');
            $direction = (string) ($request->sort['direction'] ?? 'asc');
            $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

            if (in_array($field, $allowedSortFields, true)) {
                $query->orderBy($field, $direction);
            } else {
                $query->orderBy('created_at', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $rows = $query->paginate($perPage);

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
        ]);
    }
}
