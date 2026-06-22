<?php

namespace App\Http\Controllers;

use App\Jobs\SendTenderSelectedNotificationEmail;
use Carbon\Carbon;
use App\Models\Racks;
use App\Models\TenderCompartments;
use App\Models\Tenders;
use App\Models\VendorLocation;
use App\Models\Vendors;
use App\Models\Compartments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TendersController extends Controller
{
    public function index()
    {
        return Inertia::render('racks-tenders/tenders/tenders');
    }

    public function showAll(Request $request)
    {
        $query = Tenders::query()
            ->join('racks as racks', 'tenders.rack_id', '=', 'racks.rack_id', 'inner', false)
            ->join('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->join('vendors as vendors', 'vendor_locations.vendor_id', '=', 'vendors.vendor_id', 'inner', false)
            ->select([
                'tenders.*',
                'racks.rack_name',
                DB::raw("CONCAT(vendors.vendor_name, ' - ', vendor_locations.location_name) as vendor_location_name"),
            ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('vendors.vendor_name', 'like', "%{$search}%")
                    ->orWhere('vendor_locations.location_name', 'like', "%{$search}%")
                    ->orWhere('racks.rack_name', 'like', "%{$search}%")
                    ->orWhere('tenders.tender_status', 'like', "%{$search}%");
            });
        }

        if ($request->has('filters')) {
            $filters = $request->input('filters', []);
            if (is_array($filters) && array_key_exists('tender_status', $filters) && $filters['tender_status'] !== null && $filters['tender_status'] !== '') {
                $query->where('tenders.tender_status', $filters['tender_status']);
            }
        }

        $allowedSortFields = [
            'tender_status',
            'created_at',
            'vendor_location_name',
            'rack_name',
        ];

        if ($request->has('sort')) {
            $field = (string) ($request->input('sort.field') ?? '');
            $direction = strtolower((string) ($request->input('sort.direction') ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            if (in_array($field, $allowedSortFields, true)) {
                if ($field === 'vendor_location_name') {
                    $query->orderBy('vendors.vendor_name', $direction)
                        ->orderBy('vendor_locations.location_name', $direction);
                } elseif ($field === 'rack_name') {
                    $query->orderBy('racks.rack_name', $direction);
                } else {
                    $query->orderBy("tenders.{$field}", $direction);
                }
            } else {
                $query->orderBy('tenders.created_at', 'desc');
            }
        } else {
            $query->orderBy('tenders.created_at', 'desc');
        }

        $perPage = (int) ($request->input('per_page') ?? 10);
        $perPage = max(1, min($perPage, 100));
        $tenders = $query->paginate($perPage);

        return response()->json([
            'data' => $tenders->items(),
            'meta' => [
                'current_page' => $tenders->currentPage(),
                'last_page' => $tenders->lastPage(),
                'per_page' => $tenders->perPage(),
                'total' => $tenders->total(),
                'from' => $tenders->firstItem(),
                'to' => $tenders->lastItem(),
            ],
        ]);
    }

    public function availabilityIndex()
    {
        return Inertia::render('racks-tenders/tenders/available-racks');
    }

    public function availabilityAll(Request $request)
    {
        $today = Carbon::today();
        $unavailableCompartments = DB::table('tender_compartments')
            ->select('compartment_id')
            ->where('tender_status', 'paid')
            ->whereNotNull('tender_end_date')
            ->where('tender_end_date', '<', $today)
            ->groupBy('compartment_id');

        $query = Racks::query()
            ->join('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->join('vendors as vendors', 'vendor_locations.vendor_id', '=', 'vendors.vendor_id', 'inner', false)
            ->join('compartments as compartments', 'compartments.rack_id', '=', 'racks.rack_id', 'left', false)
            ->leftJoinSub($unavailableCompartments, 'unavailable', 'compartments.compartment_id', '=', 'unavailable.compartment_id')
            ->where('racks.rack_status', 'active')
            ->select([
                'racks.rack_id',
                'racks.rack_status',
                'racks.rack_name',
                'vendor_locations.id as vendor_location_id',
                'vendors.vendor_name',
                'vendor_locations.location_name',
                DB::raw("SUM(CASE WHEN compartments.is_active = 1 AND compartments.compartment_status = 'open' AND unavailable.compartment_id IS NULL THEN 1 ELSE 0 END) as open_compartments_count"),
            ])
            ->groupBy([
                'racks.rack_id',
                'racks.rack_status',
                'racks.rack_name',
                'vendor_locations.id',
                'vendors.vendor_name',
                'vendor_locations.location_name',
            ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('vendors.vendor_name', 'like', "%{$search}%")
                    ->orWhere('vendor_locations.location_name', 'like', "%{$search}%")
                    ->orWhere('racks.rack_name', 'like', "%{$search}%");
            });
        }

        $allowedSortFields = [
            'vendor_name',
            'location_name',
            'rack_name',
            'open_compartments_count',
        ];

        if ($request->has('sort')) {
            $field = (string) ($request->input('sort.field') ?? '');
            $direction = strtolower((string) ($request->input('sort.direction') ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            if (in_array($field, $allowedSortFields, true)) {
                if ($field === 'vendor_name') {
                    $query->orderBy('vendors.vendor_name', $direction);
                } elseif ($field === 'location_name') {
                    $query->orderBy('vendor_locations.location_name', $direction);
                } elseif ($field === 'rack_name') {
                    $query->orderBy('racks.rack_name', $direction);
                } else {
                    $query->orderBy($field, $direction);
                }
            } else {
                $query->orderBy('vendors.vendor_name', 'asc')
                    ->orderBy('vendor_locations.location_name', 'asc')
                    ->orderBy('racks.rack_name', 'asc');
            }
        } else {
            $query->orderBy('vendors.vendor_name', 'asc')
                ->orderBy('vendor_locations.location_name', 'asc')
                ->orderBy('racks.rack_name', 'asc');
        }

        $perPage = (int) ($request->input('per_page') ?? 10);
        $perPage = max(1, min($perPage, 100));
        $rows = $query->paginate($perPage);

        $data = collect($rows->items())->map(fn($r) => [
            'rack_id' => (string) $r->rack_id,
            'rack_status' => (string) $r->rack_status,
            'rack_name' => (string) $r->rack_name,
            'vendor_location_id' => (string) $r->vendor_location_id,
            'vendor_location_name' => trim((string) $r->vendor_name . ' - ' . (string) $r->location_name),
            'open_compartments_count' => (int) $r->open_compartments_count,
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

    public function availabilityShow(Request $request, Racks $rack)
    {
        $rackDetails = Racks::query()
            ->leftJoin('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id')
            ->leftJoin('vendors as vendors', 'vendor_locations.vendor_id', '=', 'vendors.vendor_id')
            ->where('racks.rack_id', $rack->rack_id)
            ->first([
                'racks.rack_id',
                'racks.rack_name',
                'racks.rack_rows',
                'racks.rack_columns',
                'vendor_locations.id as vendor_location_id',
                'vendors.vendor_id as owner_vendor_id',
                DB::raw("CONCAT(vendors.vendor_name, ' - ', vendor_locations.location_name) as vendor_location_name"),
            ]);

        if (!$rackDetails) {
            abort(404);
        }

        $user = $request->user();
        $currentVendorId = $this->currentVendorId($request);
        $isOwner = $currentVendorId !== null && (string) $rackDetails->owner_vendor_id === (string) $currentVendorId;

        $today = Carbon::today();
        $unavailableCompartments = DB::table('tender_compartments')
            ->select('compartment_id')
            ->where('tender_status', 'paid')
            ->whereNotNull('tender_end_date')
            ->where('tender_end_date', '<', $today)
            ->groupBy('compartment_id');

        return Inertia::render('racks-tenders/tenders/availabe-rack-detail', [
            'rack' => [
                'rack_id' => (string) $rackDetails->rack_id,
                'rack_name' => (string) $rackDetails->rack_name,
                'rack_rows' => (int) $rackDetails->rack_rows,
                'rack_columns' => (int) $rackDetails->rack_columns,
                'vendor_location_name' => (string) $rackDetails->vendor_location_name,
                'owner_vendor_id' => (string) $rackDetails->owner_vendor_id,
            ],
            'auth' => [
                'role' => $user ? (string) $user->role : null,
                'current_vendor_id' => $currentVendorId,
                'is_owner' => $isOwner,
            ],
            'compartments' => Compartments::query()
                ->leftJoinSub($unavailableCompartments, 'unavailable', 'compartments.compartment_id', '=', 'unavailable.compartment_id')
                ->where('compartments.rack_id', $rack->rack_id)
                ->where('compartments.is_active', true)
                ->orderBy('row_index')
                ->orderBy('column_index')
                ->get([
                    'compartments.compartment_id as compartment_id',
                    'rack_id',
                    'label',
                    'row_index',
                    'column_index',
                    'min_price',
                    'min_month',
                    'compartment_status',
                    'is_active',
                    DB::raw('CASE WHEN unavailable.compartment_id IS NULL THEN 0 ELSE 1 END as is_unavailable'),
                ])
                ->map(fn($c) => [
                    'compartment_id' => (string) $c->compartment_id,
                    'rack_id' => (string) $c->rack_id,
                    'label' => (string) $c->label,
                    'row_index' => (int) $c->row_index,
                    'column_index' => (int) $c->column_index,
                    'min_price' => (string) ($c->min_price ?? '0'),
                    'min_month' => (int) ($c->min_month ?? 1),
                    'compartment_status' => (string) $c->compartment_status,
                    'is_active' => (bool) $c->is_active,
                    'is_unavailable' => (bool) $c->is_unavailable,
                ])
                ->all(),
            'myBids' => $currentVendorId ? TenderCompartments::query()
                ->join('compartments as compartments', 'tender_compartments.compartment_id', '=', 'compartments.compartment_id', 'inner', false)
                ->where('compartments.rack_id', $rack->rack_id)
                ->where('tender_compartments.vendor_id', $currentVendorId)
                ->get([
                    'tender_compartment_id',
                    'tender_compartments.compartment_id as compartment_id',
                    'bid_price',
                    'durations',
                    'product_description',
                    'tender_status',
                    'selected_at',
                ])
                ->map(fn($b) => [
                    'tender_compartment_id' => (string) $b->tender_compartment_id,
                    'compartment_id' => (string) $b->compartment_id,
                    'bid_price' => (string) $b->bid_price,
                    'product_description' => (string) $b->product_description,
                    'durations' => (int) $b->durations,
                    'tender_status' => (string) $b->tender_status,
                    'selected_at' => $b->selected_at ? (string) $b->selected_at : null,
                ])
                ->all() : [],
        ]);
    }

    public function availabilityBid(Request $request, Racks $rack)
    {
        $user = $request->user();
        if (!$user || !in_array((string) $user->role, ['vendor', 'admin']) || (string) $rack->rack_status !== 'active') {
            abort(403);
        }

        $currentVendorId = $this->currentVendorId($request);
        if (!$currentVendorId && $user->role !== 'admin') {
            abort(403);
        }

        $validated = $request->validate([
            'compartment_id' => ['required', 'uuid', 'exists:compartments,compartment_id'],
            'bid_price' => ['required', 'numeric', 'min:0'],
            'durations' => ['required', 'integer', 'min:1'],
            'product_description' => ['required', 'string', 'max:255'],
        ]);

        $rackOwnerVendorId = Racks::query()
            ->join('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->join('vendors as vendors', 'vendor_locations.vendor_id', '=', 'vendors.vendor_id', 'inner', false)
            ->where('racks.rack_id', $rack->rack_id)
            ->value('vendors.vendor_id');

        if ($rackOwnerVendorId && (string) $rackOwnerVendorId === (string) $currentVendorId && $user->role !== 'admin') {
            abort(403);
        }

        $compartment = Compartments::query()
            ->where('compartment_id', $validated['compartment_id'])
            ->where('rack_id', $rack->rack_id)
            ->first();

        if (!$compartment || !(bool) $compartment->is_active || (string) $compartment->compartment_status !== 'open') {
            return back()->withErrors([
                'compartment_id' => 'Selected compartment is not available.',
            ]);
        }

        $today = Carbon::today();
        $isUnavailable = TenderCompartments::query()
            ->where('compartment_id', (string) $validated['compartment_id'])
            ->where('tender_status', 'paid')
            ->whereNotNull('tender_end_date')
            ->where('tender_end_date', '<', $today)
            ->exists();

        if ($isUnavailable) {
            return back()->withErrors([
                'compartment_id' => 'Selected compartment is not available.',
            ]);
        }

        $minPrice = (float) ($compartment->min_price ?? 0);
        $minMonths = (int) ($compartment->min_month ?? 1);
        if ((float) $validated['bid_price'] < $minPrice) {
            return back()->withErrors([
                'bid_price' => 'Bid price must be at least ' . number_format($minPrice, 2, '.', '') . '.',
            ]);
        }
        if ((int) $validated['durations'] < $minMonths) {
            return back()->withErrors([
                'durations' => 'No. of months must be at least ' . $minMonths . '.',
            ]);
        }

        $existing = TenderCompartments::query()
            ->where('compartment_id', (string) $validated['compartment_id'])
            ->where('vendor_id', (string) $currentVendorId)
            ->first();

        if ($existing) {
            $existing->update([
                'bid_price' => $validated['bid_price'],
                'durations' => $validated['durations'],
                'product_description' => $validated['product_description'],
                'tender_status' => 'pending',
                'selected_at' => null,
                'unallocated_at' => null,
                'unallocated_by' => null,
                'unallocated_reason' => null,
            ]);
        } else {
            TenderCompartments::query()->create([
                'tender_compartment_id' => (string) Str::uuid(),
                'compartment_id' => (string) $validated['compartment_id'],
                'vendor_id' => (string) $currentVendorId,
                'bid_price' => $validated['bid_price'],
                'durations' => $validated['durations'],
                'product_description' => $validated['product_description'],
                'tender_status' => 'pending',
                'selected_at' => null,
                'unallocated_at' => null,
                'unallocated_by' => null,
                'unallocated_reason' => null,
            ]);
        }

        return back()->with([
            'success' => 'Bid submitted successfully',
        ]);
    }

    public function summaryIndex()
    {
        return Inertia::render('racks-tenders/tenders/summary');
    }

    public function summaryAll(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $today = Carbon::today();
        $unavailableCompartments = DB::table('tender_compartments')
            ->select('compartment_id')
            ->where('tender_status', 'paid')
            ->whereNotNull('tender_end_date')
            ->where('tender_end_date', '<', $today)
            ->groupBy('compartment_id');

        $query = Racks::query()
            ->join('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->join('vendors as owners', 'vendor_locations.vendor_id', '=', 'owners.vendor_id', 'inner', false)
            ->join('compartments as compartments', 'compartments.rack_id', '=', 'racks.rack_id', 'left', false)
            ->leftJoinSub($unavailableCompartments, 'unavailable', 'compartments.compartment_id', '=', 'unavailable.compartment_id')
            ->select([
                'racks.rack_id',
                'racks.rack_status',
                'racks.rack_name',
                'vendor_locations.id as vendor_location_id',
                'owners.vendor_name',
                'vendor_locations.location_name',
                DB::raw("SUM(CASE WHEN compartments.is_active = 1 AND compartments.compartment_status = 'open' AND unavailable.compartment_id IS NULL THEN 1 ELSE 0 END) as open_compartments_count"),
            ])
            ->groupBy([
                'racks.rack_id',
                'racks.rack_status',
                'racks.rack_name',
                'vendor_locations.id',
                'owners.vendor_name',
                'vendor_locations.location_name',
            ]);

        if ((string) $user->role !== 'admin') {
            $vendorIds = Vendors::query()
                ->where('user_id', $user->user_id)
                ->pluck('vendor_id')
                ->map(fn($id) => (string) $id)
                ->all();

            if ($vendorIds === []) {
                abort(403);
            }

            $query->whereIn('owners.vendor_id', $vendorIds);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('owners.vendor_name', 'like', "%{$search}%")
                    ->orWhere('vendor_locations.location_name', 'like', "%{$search}%")
                    ->orWhere('racks.rack_name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($request->input('per_page') ?? 10);
        $perPage = max(1, min($perPage, 100));
        $rows = $query->orderBy('racks.created_at', 'desc')->paginate($perPage);

        $data = collect($rows->items())->map(fn($r) => [
            'rack_id' => (string) $r->rack_id,
            'rack_status' => (string) $r->rack_status,
            'rack_name' => (string) $r->rack_name,
            'vendor_location_id' => (string) $r->vendor_location_id,
            'vendor_location_name' => trim((string) $r->vendor_name . ' - ' . (string) $r->location_name),
            'open_compartments_count' => (int) $r->open_compartments_count,
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

    public function summaryShow(Request $request, Racks $rack)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $rackDetails = Racks::query()
            ->join('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->join('vendors as owners', 'vendor_locations.vendor_id', '=', 'owners.vendor_id', 'inner', false)
            ->where('racks.rack_id', $rack->rack_id)
            ->first([
                'racks.rack_id',
                'racks.rack_name',
                'racks.rack_rows',
                'racks.rack_columns',
                'owners.vendor_id as owner_vendor_id',
                'owners.vendor_name',
                'vendor_locations.location_name',
                DB::raw("CONCAT(owners.vendor_name, ' - ', vendor_locations.location_name) as vendor_location_name"),
            ]);

        if (!$rackDetails) {
            abort(404);
        }

        if ((string) $user->role !== 'admin') {
            $vendorIds = Vendors::query()
                ->where('user_id', $user->user_id)
                ->pluck('vendor_id')
                ->map(fn($id) => (string) $id)
                ->all();

            if ($vendorIds === [] || !in_array((string) $rackDetails->owner_vendor_id, $vendorIds, true)) {
                abort(403);
            }
        }

        $compartments = Compartments::query()
            ->where('rack_id', $rack->rack_id)
            ->where('is_active', true)
            ->orderBy('row_index')
            ->orderBy('column_index')
            ->get([
                'compartment_id',
                'rack_id',
                'label',
                'row_index',
                'column_index',
                'min_price',
                'min_month',
                'compartment_status',
                'is_active',
            ])
            ->map(fn($c) => [
                'compartment_id' => (string) $c->compartment_id,
                'rack_id' => (string) $c->rack_id,
                'label' => (string) $c->label,
                'row_index' => (int) $c->row_index,
                'column_index' => (int) $c->column_index,
                'min_price' => (string) ($c->min_price ?? '0'),
                'min_month' => (int) ($c->min_month ?? 1),
                'compartment_status' => (string) $c->compartment_status,
                'is_active' => (bool) $c->is_active,
            ])
            ->all();

        $bids = TenderCompartments::query()
            ->join('compartments as compartments', 'tender_compartments.compartment_id', '=', 'compartments.compartment_id', 'inner', false)
            ->leftJoin('vendors as bid_vendors', 'tender_compartments.vendor_id', '=', 'bid_vendors.vendor_id')
            ->where('compartments.rack_id', $rack->rack_id)
            ->orderBy('tender_compartments.compartment_id')
            ->orderBy('tender_compartments.bid_price', 'asc')
            ->get([
                'tender_compartments.tender_compartment_id',
                'tender_compartments.compartment_id',
                'tender_compartments.vendor_id',
                'bid_vendors.vendor_name',
                'tender_compartments.bid_price',
                'tender_compartments.durations',
                'tender_compartments.product_description',
                'tender_compartments.tender_status',
                'tender_compartments.selected_at',
                'tender_compartments.unallocated_at',
                'tender_compartments.unallocated_by',
                'tender_compartments.unallocated_reason',
                'tender_compartments.product_description',
                'tender_compartments.tender_start_date',
                'tender_compartments.tender_end_date',
            ])
            ->map(fn($b) => [
                'tender_compartment_id' => (string) $b->tender_compartment_id,
                'compartment_id' => (string) $b->compartment_id,
                'vendor_id' => $b->vendor_id ? (string) $b->vendor_id : null,
                'vendor_name' => $b->vendor_name ? (string) $b->vendor_name : null,
                'bid_price' => (string) $b->bid_price,
                'product_description' => $b->product_description ? (string) $b->product_description : null,
                'durations' => (int) $b->durations,
                'tender_status' => (string) $b->tender_status,
                'selected_at' => $b->selected_at ? (string) $b->selected_at : null,
                'unallocated_at' => $b->unallocated_at ? (string) $b->unallocated_at : null,
                'unallocated_by' => $b->unallocated_by ? (string) $b->unallocated_by : null,
                'unallocated_reason' => $b->unallocated_reason ? (string) $b->unallocated_reason : null,
                'tender_start_date' => $b->tender_start_date ? (string) $b->tender_start_date : null,
                'tender_end_date' => $b->tender_end_date ? (string) $b->tender_end_date : null,
            ])
            ->all();

        return Inertia::render('racks-tenders/tenders/summary-detail', [
            'rack' => [
                'rack_id' => (string) $rackDetails->rack_id,
                'rack_name' => (string) $rackDetails->rack_name,
                'rack_rows' => (int) $rackDetails->rack_rows,
                'rack_columns' => (int) $rackDetails->rack_columns,
                'vendor_location_name' => (string) $rackDetails->vendor_location_name,
            ],
            'compartments' => $compartments,
            'bids' => $bids,
            'canSelect' => (string) $user->role === 'admin' || (string) $user->role === 'vendor',
            'isAdmin' => (string) $user->role === 'admin',
            'vendorOptions' => (string) $user->role === 'admin'
                ? Vendors::query()
                ->join('users', 'users.user_id', '=', 'vendors.user_id', 'inner', false)
                ->where('users.role', 'vendor')
                ->where('vendors.is_active', 'active')
                ->orderBy('vendors.vendor_name', 'asc')
                ->get(['vendors.vendor_id', 'vendors.vendor_name'])
                ->map(fn($v) => [
                    'value' => (string) $v->vendor_id,
                    'label' => (string) $v->vendor_name,
                ])
                ->all()
                : [],
        ]);
    }

    public function summarySelect(Request $request, Racks $rack)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $validated = $request->validate([
            'tender_compartment_id' => ['required', 'uuid', 'exists:tender_compartments,tender_compartment_id'],
        ]);

        $bid = TenderCompartments::query()
            ->join('compartments as compartments', 'tender_compartments.compartment_id', '=', 'compartments.compartment_id', 'inner', false)
            ->where('tender_compartment_id', $validated['tender_compartment_id'])
            ->where('compartments.rack_id', $rack->rack_id)
            ->select('tender_compartments.*')
            ->first();
        if (!$bid) {
            abort(404);
        }

        $rackOwnerVendorId = Racks::query()
            ->join('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->join('vendors as vendors', 'vendor_locations.vendor_id', '=', 'vendors.vendor_id', 'inner', false)
            ->where('racks.rack_id', $rack->rack_id)
            ->value('vendors.vendor_id');

        if ((string) $user->role !== 'admin') {
            $vendorIds = Vendors::query()
                ->where('user_id', $user->user_id)
                ->pluck('vendor_id')
                ->map(fn($id) => (string) $id)
                ->all();

            if ($vendorIds === [] || !in_array((string) $rackOwnerVendorId, $vendorIds, true)) {
                abort(403);
            }
        }

        DB::transaction(function () use ($bid) {
            TenderCompartments::query()
                ->where('compartment_id', $bid->compartment_id)
                ->where('tender_status', 'pending')
                ->update([
                    'tender_status' => 'rejected',
                    'updated_at' => now(),
                ]);

            TenderCompartments::query()
                ->where('tender_compartment_id', $bid->tender_compartment_id)
                ->update([
                    'tender_status' => 'selected',
                    'tender_start_date' => now(),
                    'tender_end_date' => now()->addDays(3),
                    'selected_at' => now(),
                    'unallocated_at' => null,
                    'unallocated_by' => null,
                    'unallocated_reason' => null,
                    'updated_at' => now(),
                ]);

            Compartments::query()
                ->where('compartment_id', $bid->compartment_id)
                ->update([
                    'compartment_status' => 'allocated',
                    'updated_at' => now(),
                ]);
        });

        $this->dispatchTenderSelectedEmail((string) $bid->tender_compartment_id);

        return back()->with([
            'success' => 'Compartment allocated successfully',
        ]);
    }

    public function summaryUnallocate(Request $request, Racks $rack)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $validated = $request->validate([
            'tender_compartment_id' => ['required', 'uuid', 'exists:tender_compartments,tender_compartment_id'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $bid = TenderCompartments::query()
            ->join('compartments as compartments', 'tender_compartments.compartment_id', '=', 'compartments.compartment_id', 'inner', false)
            ->where('tender_compartment_id', $validated['tender_compartment_id'])
            ->where('compartments.rack_id', $rack->rack_id)
            ->select('tender_compartments.*')
            ->first();
        if (!$bid) {
            abort(404);
        }
        if ((string) $bid->tender_status !== 'selected') {
            return back()->withErrors([
                'tender_compartment_id' => 'Only selected (not paid) bids can be unallocated.',
            ]);
        }

        $rackOwnerVendorId = Racks::query()
            ->join('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->join('vendors as vendors', 'vendor_locations.vendor_id', '=', 'vendors.vendor_id', 'inner', false)
            ->where('racks.rack_id', $rack->rack_id)
            ->value('vendors.vendor_id');

        if ((string) $user->role !== 'admin') {
            $vendorIds = Vendors::query()
                ->where('user_id', $user->user_id)
                ->pluck('vendor_id')
                ->map(fn($id) => (string) $id)
                ->all();

            if ($vendorIds === [] || !in_array((string) $rackOwnerVendorId, $vendorIds, true)) {
                abort(403);
            }
        }

        DB::transaction(function () use ($bid, $user, $validated) {
            TenderCompartments::query()
                ->where('tender_compartment_id', $bid->tender_compartment_id)
                ->update([
                    'tender_status' => 'pending',
                    'selected_at' => null,
                    'unallocated_at' => now(),
                    'unallocated_by' => (string) $user->user_id,
                    'unallocated_reason' => (string) $validated['reason'],
                    'updated_at' => now(),
                ]);

            Compartments::query()
                ->where('compartment_id', $bid->compartment_id)
                ->where('compartment_status', 'allocated')
                ->update([
                    'compartment_status' => 'open',
                    'updated_at' => now(),
                ]);
        });

        Log::info('Tender compartment unallocated', [
            'actor_user_id' => $user->user_id,
            'actor_role' => $user->role,
            'tender_compartment_id' => (string) $bid->tender_compartment_id,
            'compartment_id' => (string) $bid->compartment_id,
            'vendor_id' => (string) $bid->vendor_id,
            'reason' => (string) $validated['reason'],
        ]);

        return back()->with([
            'success' => 'Compartment unallocated successfully',
        ]);
    }

    public function summaryAssignVendor(Request $request, Racks $rack)
    {
        $user = $request->user();
        if (!$user || (string) $user->role !== 'admin') {
            abort(403);
        }

        $validated = $request->validate([
            'tender_compartment_id' => ['required', 'uuid', 'exists:tender_compartments,tender_compartment_id'],
            'vendor_id' => ['required', 'exists:vendors,vendor_id'],
        ]);

        $bid = TenderCompartments::query()
            ->join('compartments as compartments', 'tender_compartments.compartment_id', '=', 'compartments.compartment_id', 'inner', false)
            ->where('tender_compartment_id', $validated['tender_compartment_id'])
            ->where('compartments.rack_id', $rack->rack_id)
            ->select('tender_compartments.*')
            ->first();
        if (!$bid) {
            abort(404);
        }

        if (!in_array((string) $bid->tender_status, ['selected', 'paid'], true)) {
            return back()->withErrors([
                'tender_compartment_id' => 'Only selected/paid bids can be assigned.',
            ]);
        }

        if ($bid->vendor_id && (string) $bid->vendor_id !== '') {
            return back()->withErrors([
                'tender_compartment_id' => 'This bid already has a vendor.',
            ]);
        }

        $bid->update([
            'vendor_id' => (string) $validated['vendor_id'],
        ]);

        if ((string) $bid->tender_status === 'selected') {
            $this->dispatchTenderSelectedEmail((string) $bid->tender_compartment_id);
        }

        return back()->with([
            'success' => 'Vendor assigned successfully',
        ]);
    }

    public function summaryManualAllocate(Request $request, Racks $rack)
    {
        $user = $request->user();
        if (!$user || (string) $user->role !== 'admin') {
            abort(403);
        }

        $validated = $request->validate([
            'compartment_id' => ['required', 'uuid', 'exists:compartments,compartment_id'],
            'vendor_id' => ['required', 'exists:vendors,vendor_id'],
        ]);

        $compartment = Compartments::query()
            ->where('compartment_id', $validated['compartment_id'])
            ->where('rack_id', $rack->rack_id)
            ->first();

        if (!$compartment) {
            return back()->withErrors([
                'compartment_id' => 'Compartment does not belong to this tender rack.',
            ]);
        }

        if (!(bool) $compartment->is_active || (string) $compartment->compartment_status !== 'allocated') {
            return back()->withErrors([
                'compartment_id' => 'Only allocated compartments can be manually allocated.',
            ]);
        }

        $minPrice = (float) ($compartment->min_price ?? 0);
        $minMonths = (int) ($compartment->min_month ?? 1);

        try {
            DB::transaction(function () use ($validated, $minPrice, $minMonths) {
                $existing = TenderCompartments::query()
                    ->where('compartment_id', (string) $validated['compartment_id'])
                    ->first();

                if ($existing) {
                    if ((string) $existing->tender_status === 'paid') {
                        throw new \RuntimeException('Paid bids cannot be modified.');
                    }

                    if ($existing->vendor_id && (string) $existing->vendor_id !== '') {
                        throw new \RuntimeException('This compartment already has a vendor bid record.');
                    }

                    $existing->update([
                        'vendor_id' => (string) $validated['vendor_id'],
                        'bid_price' => $minPrice,
                        'durations' => $minMonths,
                        'product_description' => $validated['product_description'],
                        'tender_status' => 'selected',
                        'tender_start_date' => now(),
                        'tender_end_date' => now()->addDays(3),
                        'selected_at' => now(),
                        'unallocated_at' => null,
                        'unallocated_by' => null,
                        'unallocated_reason' => null,
                    ]);
                } else {
                    TenderCompartments::query()->create([
                        'tender_compartment_id' => (string) Str::uuid(),
                        'compartment_id' => (string) $validated['compartment_id'],
                        'vendor_id' => (string) $validated['vendor_id'],
                        'bid_price' => $minPrice,
                        'durations' => $minMonths,
                        'product_description' => $validated['product_description'],
                        'tender_status' => 'selected',
                        'tender_start_date' => now(),
                        'tender_end_date' => now()->addDays(3),
                        'selected_at' => now(),
                        'unallocated_at' => null,
                        'unallocated_by' => null,
                        'unallocated_reason' => null,
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors([
                'compartment_id' => $e->getMessage(),
            ]);
        }

        $selectedBid = TenderCompartments::query()
            ->where('compartment_id', (string) $validated['compartment_id'])
            ->where('vendor_id', (string) $validated['vendor_id'])
            ->where('tender_status', 'selected')
            ->latest('updated_at')
            ->first();

        if ($selectedBid) {
            $this->dispatchTenderSelectedEmail((string) $selectedBid->tender_compartment_id);
        }

        return back()->with([
            'success' => 'Allocation recorded successfully',
        ]);
    }

    public function create()
    {
        return Inertia::render('racks-tenders/tenders/create', [
            'vendorLocations' => $this->vendorLocationOptions(),
            'racks' => $this->rackList(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rack_id' => ['required', 'uuid', 'exists:racks,rack_id'],
            'tender_status' => ['required', 'in:active,inactive'],
        ]);

        Tenders::query()->create([
            'tender_id' => (string) Str::uuid(),
            'rack_id' => $validated['rack_id'],
            'tender_status' => $validated['tender_status'],
        ]);

        return redirect()->route('tenders.index')->with([
            'success' => 'Tender created successfully',
        ]);
    }

    public function edit(Tenders $tender)
    {
        return Inertia::render('racks-tenders/tenders/edit', [
            'tender' => $tender,
            'vendorLocations' => $this->vendorLocationOptions(),
            'racks' => $this->rackList(),
        ]);
    }

    public function update(Request $request, Tenders $tender)
    {
        $validated = $request->validate([
            'rack_id' => ['required', 'uuid', 'exists:racks,rack_id'],
            'tender_status' => ['required', 'in:active,inactive'],
        ]);

        $tender->update([
            'rack_id' => $validated['rack_id'],
            'tender_status' => $validated['tender_status'],
        ]);

        return redirect()->route('tenders.index')->with([
            'success' => 'Tender updated successfully',
        ]);
    }

    public function destroy(Tenders $tender)
    {
        Tenders::destroy($tender->getKey());

        return redirect()->route('tenders.index')->with([
            'success' => 'Tender deleted successfully',
        ]);
    }

    private function vendorLocationOptions(): array
    {
        return VendorLocation::query()
            ->join('vendors', 'vendors.vendor_id', '=', 'vendor_locations.vendor_id', 'inner', false)
            ->orderBy('vendors.vendor_name')
            ->orderBy('vendor_locations.location_name')
            ->get([
                'vendor_locations.id',
                'vendors.vendor_name',
                'vendor_locations.location_name',
            ])
            ->map(fn($row) => [
                'value' => (string) $row->id,
                'label' => trim((string) $row->vendor_name . ' - ' . (string) $row->location_name),
            ])
            ->all();
    }

    private function rackList(): array
    {
        return Racks::query()
            ->orderBy('rack_name', 'asc')
            ->get([
                'rack_id',
                'vendor_location_id',
                'rack_name',
                'rack_rows',
                'rack_columns',
                'rack_status',
            ])
            ->map(fn($r) => [
                'rack_id' => (string) $r->rack_id,
                'vendor_location_id' => (string) $r->vendor_location_id,
                'rack_name' => (string) $r->rack_name,
                'rack_rows' => (string) $r->rack_rows,
                'rack_columns' => (string) $r->rack_columns,
                'rack_status' => (string) $r->rack_status,
            ])
            ->all();
    }

    private function currentVendorId(Request $request): ?string
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        return Vendors::query()
            ->where('user_id', $user->user_id)
            ->pluck('vendor_id')
            ->map(fn($id) => (string) $id)
            ->first();
    }

    private function dispatchTenderSelectedEmail(string $tenderCompartmentId): void
    {
        $selection = TenderCompartments::query()
            ->join('compartments as compartments', 'tender_compartments.compartment_id', '=', 'compartments.compartment_id', 'inner', false)
            ->join('racks as racks', 'compartments.rack_id', '=', 'racks.rack_id', 'inner', false)
            ->join('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->join('vendors as selected_vendors', 'tender_compartments.vendor_id', '=', 'selected_vendors.vendor_id', 'inner', false)
            ->where('tender_compartments.tender_compartment_id', $tenderCompartmentId)
            ->select([
                'tender_compartments.tender_compartment_id',
                'tender_compartments.bid_price',
                'tender_compartments.durations',
                'tender_compartments.tender_end_date',
                'selected_vendors.vendor_name',
                'selected_vendors.email',
                'racks.rack_name',
                'vendor_locations.location_name',
                'compartments.label as compartment_label',
            ])
            ->first();

        if (!$selection || !(string) $selection->email || !$selection->tender_end_date) {
            return;
        }

        SendTenderSelectedNotificationEmail::dispatch(
            (string) $selection->email,
            [
                'vendor_name' => (string) $selection->vendor_name,
                'vendor_location_name' => trim((string) $selection->location_name),
                'rack_name' => (string) $selection->rack_name,
                'compartment_label' => (string) $selection->compartment_label,
                'bid_price' => number_format((float) $selection->bid_price, 2, '.', ''),
                'durations' => (int) $selection->durations,
                'tender_end_date' => Carbon::parse($selection->tender_end_date)->format('d M Y h:i A'),
            ]
        )->delay(now()->addMinute());
    }
}
