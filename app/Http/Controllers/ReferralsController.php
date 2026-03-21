<?php

namespace App\Http\Controllers;

use App\Models\Referrals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReferralsController extends Controller
{
    public function index()
    {
        return Inertia::render('referrals/referrals');
    }

    public function showAll(Request $request)
    {
        $query = Referrals::query()
            ->join('users as referrer', 'referrals.user_id', '=', 'referrer.user_id')
            ->select([
                'referrals.user_id',
                'referrer.first_name',
                'referrer.last_name',
                'referrer.email',
                DB::raw('COUNT(*) as total_referrals'),
                DB::raw("SUM(CASE WHEN referrals.referral_status = 'pending' THEN 1 ELSE 0 END) as pending_count"),
                DB::raw("SUM(CASE WHEN referrals.referral_status = 'qualified' THEN 1 ELSE 0 END) as qualified_count"),
                DB::raw("SUM(CASE WHEN referrals.referral_status = 'rewarded' THEN 1 ELSE 0 END) as rewarded_count"),
                DB::raw("SUM(CASE WHEN referrals.referral_status = 'revoked' THEN 1 ELSE 0 END) as revoked_count"),
                DB::raw('MAX(referrals.referral_date) as latest_referral_date'),
            ])
            ->groupBy('referrals.user_id', 'referrer.first_name', 'referrer.last_name', 'referrer.email');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('referrer.first_name', 'like', "%{$search}%")
                    ->orWhere('referrer.last_name', 'like', "%{$search}%")
                    ->orWhere('referrer.email', 'like', "%{$search}%")
                    ->orWhere('referrals.referral_code', 'like', "%{$search}%");
            });
        }

        $allowedSortFields = [
            'email',
            'first_name',
            'last_name',
            'total_referrals',
            'pending_count',
            'qualified_count',
            'rewarded_count',
            'revoked_count',
            'latest_referral_date',
        ];

        if ($request->has('sort')) {
            $field = (string) ($request->sort['field'] ?? '');
            $direction = (string) ($request->sort['direction'] ?? 'asc');
            $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

            if (in_array($field, $allowedSortFields, true)) {
                $query->orderBy($field, $direction);
            } else {
                $query->orderBy('latest_referral_date', 'desc');
            }
        } else {
            $query->orderBy('latest_referral_date', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $referrals = $query->paginate($perPage);

        return response()->json([
            'data' => $referrals->items(),
            'meta' => [
                'current_page' => $referrals->currentPage(),
                'last_page' => $referrals->lastPage(),
                'per_page' => $referrals->perPage(),
                'total' => $referrals->total(),
                'from' => $referrals->firstItem(),
                'to' => $referrals->lastItem(),
            ],
        ]);
    }
}

