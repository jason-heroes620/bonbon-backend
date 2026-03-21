<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Discounts;
use App\Models\Events;
use App\Models\Memberships;
use App\Models\Orders;
use App\Models\Payments;
use App\Models\Referrals;
use App\Models\User;
use App\Models\Vendors;
use App\Models\Vouchers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $startOfMonth = $now->copy()->startOfMonth();

        $revenueMtd = (float) Payments::query()
            ->where('payment_status', 1)
            ->whereBetween('payment_date', [$startOfMonth, $now])
            ->sum('payment_amount');

        $ordersToday = (int) Orders::query()
            ->whereDate('order_date', $today->toDateString())
            ->count();

        $ordersMtd = (int) Orders::query()
            ->whereBetween('order_date', [$startOfMonth->toDateString(), $now->toDateString()])
            ->count();

        $newUsers7d = (int) User::query()
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->count();

        $activeVendors = (int) Vendors::query()
            ->where('is_active', 'active')
            ->count();

        $activeMemberships = (int) Memberships::query()
            ->where('is_active', true)
            ->count();

        $membershipExpiring7d = (int) Memberships::query()
            ->where('is_active', true)
            ->whereNotNull('membership_end_date')
            ->whereBetween('membership_end_date', [$today->toDateString(), $now->copy()->addDays(7)->toDateString()])
            ->count();

        $upcomingEvents30d = (int) Events::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->whereBetween('event_start_date', [$today->toDateString(), $now->copy()->addDays(30)->toDateString()])
            ->count();

        $rangeStart = $now->copy()->subDays(29)->startOfDay();
        $days = collect(range(0, 29))->map(function ($i) use ($rangeStart) {
            return $rangeStart->copy()->addDays($i)->toDateString();
        });

        $revenueByDay = Payments::query()
            ->where('payment_status', 1)
            ->where('payment_date', '>=', $rangeStart)
            ->selectRaw('date(payment_date) as day, SUM(payment_amount) as total')
            ->groupBy(DB::raw('date(payment_date)'))
            ->orderBy('day')
            ->get()
            ->pluck('total', 'day')
            ->map(fn($v) => (float) $v)
            ->all();

        $usersByDay = User::query()
            ->where('created_at', '>=', $rangeStart)
            ->selectRaw('date(created_at) as day, COUNT(*) as total')
            ->groupBy(DB::raw('date(created_at)'))
            ->orderBy('day')
            ->get()
            ->pluck('total', 'day')
            ->map(fn($v) => (int) $v)
            ->all();

        $referralsByStatus = Referrals::query()
            ->select('referral_status', DB::raw('COUNT(*) as total'))
            ->groupBy('referral_status')
            ->pluck('total', 'referral_status')
            ->map(fn($v) => (int) $v)
            ->all();

        $recentFailedPayments = Payments::query()
            ->where('payment_status', '!=', 1)
            ->orderBy('payment_date', 'desc')
            ->limit(5)
            ->get([
                'payment_id',
                'order_no',
                'payment_amount',
                'payment_method',
                'payment_date',
                'payment_status',
            ])
            ->map(function ($p) {
                return [
                    'payment_id' => $p->payment_id,
                    'order_no' => $p->order_no,
                    'payment_amount' => (float) $p->payment_amount,
                    'payment_method' => $p->payment_method,
                    'payment_date' => $p->payment_date ? Carbon::parse($p->payment_date)->toIso8601String() : null,
                    'payment_status' => (int) $p->payment_status,
                ];
            })
            ->all();

        $expiringVouchers = Vouchers::query()
            ->where('voucher_status', true)
            ->whereBetween('voucher_expiry_date', [$today->toDateString(), $now->copy()->addDays(7)->toDateString()])
            ->orderBy('voucher_expiry_date')
            ->limit(5)
            ->get(['voucher_id', 'voucher_name', 'voucher_expiry_date'])
            ->map(fn($v) => [
                'voucher_id' => $v->voucher_id,
                'voucher_name' => $v->voucher_name,
                'voucher_expiry_date' => $v->voucher_expiry_date,
            ])
            ->all();

        $expiringDiscounts = Discounts::query()
            ->where('is_active', true)
            ->whereBetween('discount_end_date', [$today->toDateString(), $now->copy()->addDays(7)->toDateString()])
            ->orderBy('discount_end_date')
            ->limit(5)
            ->get(['discount_id', 'discount_code', 'discount_name', 'discount_end_date'])
            ->map(fn($d) => [
                'discount_id' => $d->discount_id,
                'discount_code' => $d->discount_code,
                'discount_name' => $d->discount_name,
                'discount_end_date' => $d->discount_end_date,
            ])
            ->all();

        $stalePendingReferrals = Referrals::query()
            ->join('users as referrer', 'referrals.user_id', '=', 'referrer.user_id')
            ->where('referrals.referral_status', 'pending')
            ->where('referrals.referral_date', '<=', $now->copy()->subDays(14)->toDateString())
            ->orderBy('referrals.referral_date')
            ->limit(5)
            ->get([
                'referrals.referral_id',
                'referrals.referral_code',
                'referrals.referral_date',
                'referrer.first_name',
                'referrer.last_name',
                'referrer.email',
            ])
            ->map(fn($r) => [
                'referral_id' => $r->referral_id,
                'referral_code' => $r->referral_code,
                'referral_date' => $r->referral_date,
                'referrer_name' => trim($r->first_name . ' ' . $r->last_name),
                'referrer_email' => $r->email,
            ])
            ->all();

        return Inertia::render('dashboard/dashboard', [
            'kpis' => [
                'revenue_mtd' => $revenueMtd,
                'orders_today' => $ordersToday,
                'orders_mtd' => $ordersMtd,
                'new_users_7d' => $newUsers7d,
                'active_vendors' => $activeVendors,
                'active_memberships' => $activeMemberships,
                'membership_expiring_7d' => $membershipExpiring7d,
                'upcoming_events_30d' => $upcomingEvents30d,
            ],
            'charts' => [
                'days' => $days->all(),
                'revenue_30d' => $days->map(fn($d) => [
                    'day' => $d,
                    'total' => (float) ($revenueByDay[$d] ?? 0),
                ])->all(),
                'new_users_30d' => $days->map(fn($d) => [
                    'day' => $d,
                    'total' => (int) ($usersByDay[$d] ?? 0),
                ])->all(),
                'referrals_by_status' => [
                    'pending' => (int) ($referralsByStatus['pending'] ?? 0),
                    'qualified' => (int) ($referralsByStatus['qualified'] ?? 0),
                    'rewarded' => (int) ($referralsByStatus['rewarded'] ?? 0),
                    'revoked' => (int) ($referralsByStatus['revoked'] ?? 0),
                ],
            ],
            'attention' => [
                'recent_failed_payments' => $recentFailedPayments,
                'expiring_vouchers_7d' => $expiringVouchers,
                'expiring_discounts_7d' => $expiringDiscounts,
                'stale_pending_referrals' => $stalePendingReferrals,
            ],
        ]);
    }
}
