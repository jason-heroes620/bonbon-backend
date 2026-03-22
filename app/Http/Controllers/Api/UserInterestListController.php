<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserInterestList;
use Illuminate\Http\Request;

class UserInterestListController extends Controller
{
    public function registerInterestList(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim((string) $validated['email']));

        $record = UserInterestList::query()->firstOrCreate([
            'email' => $email,
        ]);

        return response()->json([
            'message' => $record->wasRecentlyCreated
                ? 'Interest list registered.'
                : 'Email already registered.',
        ]);
    }

    public function getListCount(Request $request)
    {
        $count = UserInterestList::query()->count();

        return response()->json([
            'count' => $count,
        ]);
    }
}
