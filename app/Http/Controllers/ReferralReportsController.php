<?php

namespace App\Http\Controllers;

use App\Models\ReferralEarnings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReferralReportsController extends Controller
{
    public function index(Request $request)
    {
        $auth = $request->user();
        if (!$auth || $auth->role !== 'admin') {
            abort(403);
        }

        $now = now();
        $previous = $now->copy()->subMonth();

        $years = [];
        for ($y = (int) $now->format('Y'); $y >= (int) $now->copy()->subYears(5)->format('Y'); $y--) {
            $years[] = $y;
        }

        return Inertia::render('reports/referral-report', [
            'defaultMonth' => (int) $previous->format('n'),
            'defaultYear' => (int) $previous->format('Y'),
            'years' => $years,
        ]);
    }

    public function users(Request $request)
    {
        $auth = $request->user();
        if (!$auth || $auth->role !== 'admin') {
            abort(403);
        }

        $membershipType = strtoupper((string) $request->input('membership_type', 'all'));
        $types = $membershipType === 'ALL' ? ['KOL', 'FOBB'] : [$membershipType];
        $types = array_values(array_intersect($types, ['KOL', 'FOBB']));

        $query = User::query()
            ->join('user_memberships', 'user_memberships.user_id', '=', 'users.user_id')
            ->join('memberships', 'memberships.membership_id', '=', 'user_memberships.membership_id')
            ->where('user_memberships.membership_status', 'active')
            ->whereIn(DB::raw('UPPER(memberships.membership_type)'), $types)
            ->select([
                'users.user_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'memberships.membership_type',
            ])
            ->orderBy('users.email', 'asc');

        $items = $query->get()->map(fn ($u) => [
            'value' => $u->user_id,
            'label' => trim(($u->email ?? '') . ' - ' . ($u->first_name ?? '') . ' ' . ($u->last_name ?? '') . ' (' . strtoupper((string) $u->membership_type) . ')'),
        ])->values();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function data(Request $request)
    {
        $auth = $request->user();
        if (!$auth || $auth->role !== 'admin') {
            abort(403);
        }

        $validated = $request->validate([
            'membership_type' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $membershipType = strtoupper((string) ($validated['membership_type'] ?? 'all'));
        $types = $membershipType === 'ALL' ? ['KOL', 'FOBB'] : [$membershipType];
        $types = array_values(array_intersect($types, ['KOL', 'FOBB']));
        if (empty($types)) {
            $types = ['KOL', 'FOBB'];
        }

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $userId = $validated['user_id'] ?? null;
        if ($userId === 'all' || $userId === '') {
            $userId = null;
        }

        $earnings = ReferralEarnings::query()
            ->select([
                'user_id',
                DB::raw('SUM(amount) as total_payable'),
                DB::raw('COUNT(*) as total_referrals'),
            ])
            ->where('month', $month)
            ->where('year', $year)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->groupBy('user_id');

        $rows = User::query()
            ->joinSub($earnings, 'earnings', function ($join) {
                $join->on('earnings.user_id', '=', 'users.user_id');
            })
            ->join('user_memberships', 'user_memberships.user_id', '=', 'users.user_id')
            ->join('memberships', 'memberships.membership_id', '=', 'user_memberships.membership_id')
            ->where('user_memberships.membership_status', 'active')
            ->whereIn(DB::raw('UPPER(memberships.membership_type)'), $types)
            ->select([
                'users.user_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                DB::raw('UPPER(memberships.membership_type) as membership_type'),
                DB::raw('earnings.total_payable as total_payable'),
                DB::raw('earnings.total_referrals as total_referrals'),
            ])
            ->orderByDesc('earnings.total_payable')
            ->get()
            ->map(fn ($r) => [
                'user_id' => $r->user_id,
                'first_name' => $r->first_name,
                'last_name' => $r->last_name,
                'email' => $r->email,
                'membership_type' => $r->membership_type,
                'total_referrals' => (int) $r->total_referrals,
                'total_payable' => (int) $r->total_payable,
            ])
            ->values();

        return response()->json([
            'data' => $rows,
        ]);
    }
}

