<?php

namespace App\Http\Controllers;

use App\Models\Memberships;
use App\Models\Referrals;
use App\Models\User;
use App\Models\UserMemberships;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KolController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        return Inertia::render('kol/kol');
    }

    public function showAll(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        $query = User::query()
            ->join('user_memberships', 'user_memberships.user_id', '=', 'users.user_id')
            ->join('memberships', 'memberships.membership_id', '=', 'user_memberships.membership_id')
            ->where('user_memberships.membership_status', 'active')
            ->whereRaw('UPPER(memberships.membership_type) = ?', ['KOL'])
            ->select([
                'users.user_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.contact_no',
                'users.is_active',
                'users.role',
                'user_memberships.membership_end_date',
            ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('users.email', 'like', "%{$search}%")
                    ->orWhere('users.first_name', 'like', "%{$search}%")
                    ->orWhere('users.last_name', 'like', "%{$search}%");
            });
        }

        $allowedSortFields = [
            'email',
            'membership_end_date',
            'created_at',
        ];

        if ($request->has('sort')) {
            $field = (string) ($request->sort['field'] ?? '');
            $direction = (string) ($request->sort['direction'] ?? 'asc');
            $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

            if (in_array($field, $allowedSortFields, true)) {
                if ($field === 'created_at') {
                    $query->orderBy('users.created_at', $direction);
                } else {
                    $query->orderBy($field, $direction);
                }
            } else {
                $query->orderBy('users.created_at', 'desc');
            }
        } else {
            $query->orderBy('users.created_at', 'desc');
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

    public function show(Request $request, User $user)
    {
        $auth = $request->user();
        if (!$auth || $auth->role !== 'admin') {
            abort(403);
        }

        $isKol = UserMemberships::query()
            ->join('memberships', 'memberships.membership_id', '=', 'user_memberships.membership_id')
            ->where('user_memberships.user_id', $user->user_id)
            ->where('user_memberships.membership_status', 'active')
            ->whereRaw('UPPER(memberships.membership_type) = ?', ['KOL'])
            ->exists();

        if (!$isKol) {
            abort(404);
        }

        $stats = Referrals::query()
            ->where('user_id', $user->user_id)
            ->select('referral_status', DB::raw('COUNT(*) as total'))
            ->groupBy('referral_status')
            ->pluck('total', 'referral_status')
            ->map(fn ($v) => (int) $v)
            ->all();

        return Inertia::render('kol/show', [
            'kolUser' => $user,
            'referralStats' => [
                'pending' => (int) ($stats['pending'] ?? 0),
                'qualified' => (int) ($stats['qualified'] ?? 0),
                'rewarded' => (int) ($stats['rewarded'] ?? 0),
                'revoked' => (int) ($stats['revoked'] ?? 0),
            ],
        ]);
    }

    public function referrals(Request $request, User $user)
    {
        $auth = $request->user();
        if (!$auth || $auth->role !== 'admin') {
            abort(403);
        }

        $query = Referrals::query()
            ->join('users as referee', 'referrals.referee_id', '=', 'referee.user_id')
            ->where('referrals.user_id', $user->user_id)
            ->select([
                'referrals.referral_id',
                'referrals.referral_code',
                'referrals.referral_status',
                'referrals.referral_date',
                'referrals.qualified_at',
                'referrals.qualifying_order_no',
                'referrals.cycle',
                'referee.user_id as referee_user_id',
                'referee.first_name as referee_first_name',
                'referee.last_name as referee_last_name',
                'referee.email as referee_email',
            ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('referee.email', 'like', "%{$search}%")
                    ->orWhere('referee.first_name', 'like', "%{$search}%")
                    ->orWhere('referee.last_name', 'like', "%{$search}%")
                    ->orWhere('referrals.referral_code', 'like', "%{$search}%")
                    ->orWhere('referrals.referral_status', 'like', "%{$search}%");
            });
        }

        if ($request->has('sort')) {
            $field = (string) ($request->sort['field'] ?? '');
            $direction = (string) ($request->sort['direction'] ?? 'asc');
            $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

            $fieldMap = [
                'referral_date' => 'referrals.referral_date',
                'referral_status' => 'referrals.referral_status',
                'cycle' => 'referrals.cycle',
                'referee_email' => 'referee.email',
            ];

            if (array_key_exists($field, $fieldMap)) {
                $query->orderBy($fieldMap[$field], $direction);
            } else {
                $query->orderBy('referrals.referral_date', 'desc');
            }
        } else {
            $query->orderBy('referrals.referral_date', 'desc');
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

