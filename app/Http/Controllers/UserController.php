<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('users/users');
    }

    public function showAll(Request $request)
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
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
            $query->orderBy('users.created_at', 'desc');
        }
        $perPage = $request->per_page ?? 10;
        $users = $query->paginate($perPage);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
        ]);
    }

    public function options(Request $request)
    {
        $q = (string) $request->input('q', '');
        $users = User::query()
            ->select('user_id', 'first_name', 'last_name', 'email')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->where('role', 'user')
            ->orderBy('first_name', 'asc')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $users,
        ]);
    }

    public function getUserList()
    {
        $users = User::query()
            ->select('user_id', 'first_name', 'last_name', 'email')
            ->where('role', 'user')
            ->where('is_active', true)
            ->orderBy('first_name', 'asc')
            ->orderBy('last_name', 'asc')
            ->get()
            ->map(function ($u) {
                $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                $label = $name !== '' ? $name : ($u->email ?? '');
                if (!empty($u->email) && $label !== $u->email) {
                    $label .= ' (' . $u->email . ')';
                }
                return [
                    'value' => $u->user_id,
                    'label' => $label,
                ];
            });

        return response()->json($users);
    }

    public function edit(Request $request, User $user)
    {
        return Inertia::render('users/edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->getKey(), $user->getKeyName()),
            ],
            'role' => ['required', Rule::in(['user', 'vendor', 'admin'])],
            'is_active' => ['required', 'boolean'],
        ]);

        $user->update($validated);

        return redirect()->back();
    }
}
