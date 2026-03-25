<?php

namespace App\Http\Controllers;

use App\Models\Memberships;
use App\Models\ReferralCodes;
use App\Models\User;
use App\Models\UserMemberships;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        $memberships = Memberships::query()
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get([
                'membership_id',
                'membership_code',
                'membership_name',
                'membership_type',
            ]);

        $userMembership = UserMemberships::query()
            ->where('user_id', $user->user_id)
            ->orderBy('membership_start_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($userMembership) {
            $userMembership->membership_start_date = $userMembership->membership_start_date
                ? Carbon::parse($userMembership->membership_start_date)->toDateString()
                : null;
            $userMembership->membership_end_date = $userMembership->membership_end_date
                ? Carbon::parse($userMembership->membership_end_date)->toDateString()
                : null;
        }

        return Inertia::render('users/edit', [
            'user' => $user,
            'memberships' => $memberships,
            'userMembership' => $userMembership,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:20'],
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
            'membership_id' => ['nullable', 'uuid', 'exists:memberships,membership_id'],
            'membership_status' => ['nullable', Rule::in(['active', 'inactive', 'cancelled', 'expired'])],
            'membership_start_date' => ['nullable', 'date'],
            'membership_end_date' => ['nullable', 'date', 'after_or_equal:membership_start_date'],
            'auto_renew' => ['nullable', 'boolean'],
            'inactive_reason' => ['nullable', 'string', 'max:255'],
            'generate_referral_code' => ['nullable', 'boolean'],
        ]);

        $actor = $request->user();
        $now = now();

        $baseUserUpdates = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'contact_no' => $validated['contact_no'] ?? null,
            'role' => $validated['role'],
            'is_active' => (bool) $validated['is_active'],
        ];

        if (array_key_exists('email', $validated)) {
            $baseUserUpdates['email'] = $validated['email'];
        }

        DB::transaction(function () use ($user, $validated, $baseUserUpdates, $now) {
            $user->update($baseUserUpdates);

            $membershipId = $validated['membership_id'] ?? null;
            if ($membershipId) {
                $start = isset($validated['membership_start_date']) && $validated['membership_start_date']
                    ? Carbon::parse($validated['membership_start_date'])
                    : Carbon::parse($now)->startOfDay();

                $end = isset($validated['membership_end_date']) && $validated['membership_end_date']
                    ? Carbon::parse($validated['membership_end_date'])
                    : null;

                if (!$end) {
                    $membership = Memberships::query()->find($membershipId);
                    if ($membership && is_numeric($membership->duration) && !empty($membership->duration_unit)) {
                        $duration = (int) $membership->duration;
                        $unit = (string) $membership->duration_unit;
                        if ($duration > 0) {
                            if ($unit === 'days') {
                                $end = $start->copy()->addDays($duration);
                            } elseif ($unit === 'months') {
                                $end = $start->copy()->addMonths($duration);
                            } elseif ($unit === 'years') {
                                $end = $start->copy()->addYears($duration);
                            }
                        }
                    }
                }

                $membershipPayload = [
                    'user_id' => $user->user_id,
                    'membership_id' => $membershipId,
                    'membership_status' => (string) ($validated['membership_status'] ?? 'active'),
                    'membership_start_date' => $start->toDateString(),
                    'membership_end_date' => $end ? $end->toDateString() : null,
                    'inactive_reason' => $validated['inactive_reason'] ?? null,
                    'auto_renew' => (bool) ($validated['auto_renew'] ?? false),
                ];

                $existingMembership = UserMemberships::query()
                    ->where('user_id', $user->user_id)
                    ->orderBy('membership_start_date', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($existingMembership) {
                    $existingMembership->update($membershipPayload);
                } else {
                    UserMemberships::create($membershipPayload);
                }
            }

            $shouldGenerateReferralCode = (bool) ($validated['generate_referral_code'] ?? false);
            if ($shouldGenerateReferralCode) {
                $tries = 0;
                $referralCode = null;
                do {
                    $tries++;
                    $candidate = strtoupper(Str::random(8));
                    $exists = ReferralCodes::query()->where('referral_code', $candidate)->exists();
                    if (!$exists) {
                        $referralCode = $candidate;
                        break;
                    }
                } while ($tries < 20);

                if (!$referralCode) {
                    throw new \RuntimeException('Unable to generate a unique referral code.');
                }

                ReferralCodes::query()
                    ->where('user_id', $user->user_id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                ReferralCodes::create([
                    'user_id' => $user->user_id,
                    'campaign_name' => 'Admin',
                    'referral_code' => $referralCode,
                    'code_effective_date' => $now->toDateString(),
                    'code_expiry_date' => $now->copy()->addYear()->toDateString(),
                    'usage_count' => 0,
                    'max_usage' => 0,
                    'is_active' => true,
                ]);

                User::query()->where('user_id', $user->user_id)->update([
                    'referral_code' => $referralCode,
                ]);
            }
        });

        Log::info('User updated via admin edit page', [
            'actor_user_id' => $actor ? $actor->user_id : null,
            'actor_email' => $actor ? $actor->email : null,
            'target_user_id' => $user->user_id,
            'target_email' => $user->email,
            'updated_at' => $now->toDateTimeString(),
            'membership_id' => $validated['membership_id'] ?? null,
            'membership_status' => $validated['membership_status'] ?? null,
            'generate_referral_code' => (bool) ($validated['generate_referral_code'] ?? false),
        ]);

        return redirect()->back()->with([
            'success' => 'User updated successfully',
        ]);
    }
}
