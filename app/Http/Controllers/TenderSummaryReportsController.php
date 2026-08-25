<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TenderSummaryReportsController extends Controller
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
        for ($year = (int) $now->format('Y'); $year >= (int) $now->copy()->subYears(5)->format('Y'); $year--) {
            $years[] = $year;
        }

        return Inertia::render('reports/tender-summary-report', [
            'defaultMonth' => (int) $previous->format('n'),
            'defaultYear' => (int) $previous->format('Y'),
            'years' => $years,
        ]);
    }

    public function data(Request $request)
    {
        $auth = $request->user();
        if (!$auth || $auth->role !== 'admin') {
            abort(403);
        }

        $validated = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        $query = DB::table('tender_compartments')
            ->join('compartments', 'tender_compartments.compartment_id', '=', 'compartments.compartment_id')
            ->join('racks', 'compartments.rack_id', '=', 'racks.rack_id')
            ->join('vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id')
            ->join('vendors as owners', 'vendor_locations.vendor_id', '=', 'owners.vendor_id')
            ->leftJoin('vendors as payees', 'tender_compartments.vendor_id', '=', 'payees.vendor_id')
            ->join('order_items', function ($join) {
                $join->on('order_items.source_id', '=', 'tender_compartments.tender_compartment_id')
                    ->where('order_items.line_type', '=', 'contract');
            })
            ->join('orders', 'order_items.order_id', '=', 'orders.order_id')
            ->join('payments', 'payments.order_no', '=', 'orders.order_no')
            ->where('tender_compartments.tender_status', 'paid')
            ->where('payments.payment_status', 1)
            ->whereMonth('payments.payment_date', $month)
            ->whereYear('payments.payment_date', $year)
            ->select([
                'owners.vendor_id as owner_vendor_id',
                'owners.vendor_name as owner_vendor_name',
                'tender_compartments.vendor_id as payee_vendor_id',
                DB::raw('COALESCE(payees.vendor_name, tender_compartments.vendor_id) as payee_vendor_name'),
                DB::raw('COUNT(DISTINCT tender_compartments.tender_compartment_id) as contracts_count'),
                DB::raw('ROUND(SUM(tender_compartments.bid_price * tender_compartments.durations), 2) as total_payable'),
                DB::raw('MAX(payments.payment_date) as latest_payment_date'),
            ])
            ->groupBy([
                'owners.vendor_id',
                'owners.vendor_name',
                'tender_compartments.vendor_id',
                'payees.vendor_name',
            ]);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where('owners.vendor_name', 'like', "%{$search}%");
        }

        $allowedSortFields = [
            'owner_vendor_name',
            'payee_vendor_name',
            'payee_vendor_id',
            'contracts_count',
            'total_payable',
            'latest_payment_date',
        ];

        if ($request->has('sort')) {
            $field = (string) ($request->input('sort.field') ?? '');
            $direction = strtolower((string) ($request->input('sort.direction') ?? 'asc')) === 'desc' ? 'desc' : 'asc';

            if (in_array($field, $allowedSortFields, true)) {
                $query->orderBy($field, $direction);
            } else {
                $query->orderByDesc('total_payable')
                    ->orderBy('owner_vendor_name');
            }
        } else {
            $query->orderByDesc('total_payable')
                ->orderBy('owner_vendor_name');
        }

        $perPage = (int) ($request->input('per_page') ?? 10);
        $perPage = max(1, min($perPage, 100));
        $rows = $query->paginate($perPage);

        $data = collect($rows->items())->map(fn($row) => [
            'owner_vendor_id' => (string) $row->owner_vendor_id,
            'owner_vendor_name' => (string) $row->owner_vendor_name,
            'payee_vendor_id' => (string) $row->payee_vendor_id,
            'payee_vendor_name' => (string) $row->payee_vendor_name,
            'contracts_count' => (int) $row->contracts_count,
            'total_payable' => number_format((float) $row->total_payable, 2, '.', ''),
            'latest_payment_date' => $row->latest_payment_date
                ? Carbon::parse($row->latest_payment_date)->toDateTimeString()
                : null,
        ])->all();

        return response()->json([
            'data' => $data,
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
