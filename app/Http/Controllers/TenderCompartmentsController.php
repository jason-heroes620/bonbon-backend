<?php

namespace App\Http\Controllers;

use App\Models\Compartments;
use App\Models\Racks;
use App\Models\TenderCompartments;
use App\Models\VendorLocation;
use App\Models\Vendors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TenderCompartmentsController extends Controller
{
    public function index(Request $request)
    {
        $rackId = (string) ($request->query('rack_id') ?? '');
        $rack = null;
        if ($rackId !== '') {
            $rack = Racks::query()->where('rack_id', $rackId)->first();
        }

        return Inertia::render('racks-tenders/tender-compartments/tender-compartments', [
            'rack' => $rack ? [
                'rack_id' => (string) $rack->rack_id,
                'rack_name' => (string) $rack->rack_name,
            ] : null,
        ]);
    }

    public function showAll(Request $request)
    {
        $query = TenderCompartments::query()
            ->join('compartments as compartments', 'tender_compartments.compartment_id', '=', 'compartments.compartment_id', 'inner', false)
            ->join('racks as racks', 'compartments.rack_id', '=', 'racks.rack_id', 'inner', false)
            ->join('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->join('vendors as vendors', 'vendor_locations.vendor_id', '=', 'vendors.vendor_id', 'inner', false)
            ->leftJoin('vendors as bid_vendors', 'tender_compartments.vendor_id', '=', 'bid_vendors.vendor_id', 'left', false)
            ->select([
                'tender_compartments.*',
                'racks.rack_id',
                'racks.rack_name',
                DB::raw("CONCAT(vendors.vendor_name, ' - ', vendor_locations.location_name) as vendor_location_name"),
                'bid_vendors.vendor_name as vendor_name',
                'compartments.label as compartment_label',
            ]);

        $rackId = (string) ($request->query('rack_id') ?? '');
        if ($rackId !== '') {
            $query->where('racks.rack_id', $rackId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('bid_vendors.vendor_name', 'like', "%{$search}%")
                    ->orWhere('racks.rack_name', 'like', "%{$search}%")
                    ->orWhere('compartments.label', 'like', "%{$search}%")
                    ->orWhere('tender_compartments.tender_status', 'like', "%{$search}%");
            });
        }

        $allowedSortFields = [
            'vendor_location_name',
            'rack_name',
            'compartment_label',
            'vendor_name',
            'bid_price',
            'durations',
            'tender_status',
            'selected_at',
            'created_at',
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
                } elseif ($field === 'vendor_name') {
                    $query->orderBy('bid_vendors.vendor_name', $direction);
                } elseif ($field === 'compartment_label') {
                    $query->orderBy('compartments.label', $direction);
                } else {
                    $query->orderBy("tender_compartments.{$field}", $direction);
                }
            } else {
                $query->orderBy('tender_compartments.created_at', 'desc');
            }
        } else {
            $query->orderBy('tender_compartments.created_at', 'desc');
        }

        $perPage = (int) ($request->input('per_page') ?? 10);
        $perPage = max(1, min($perPage, 100));
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

    public function create()
    {
        return Inertia::render('racks-tenders/tender-compartments/create', [
            'vendors' => $this->vendorOptions(),
            'vendorLocations' => $this->vendorLocationOptions(),
            'racks' => $this->rackList(),
            'compartments' => $this->compartmentList(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rack_id' => ['required', 'uuid', 'exists:racks,rack_id'],
            'compartment_id' => ['required', 'uuid', 'exists:compartments,compartment_id'],
            'vendor_id' => ['required', 'exists:vendors,vendor_id'],
            'bid_price' => ['required', 'numeric', 'min:0'],
            'durations' => ['required', 'integer', 'min:1'],
            'product_description' => ['required', 'string', 'max:255'],
            'tender_status' => ['required', 'in:pending,selected,paid,expired,rejected'],
            'selected_at' => ['nullable', 'date'],
        ]);

        $compartment = Compartments::query()
            ->where('compartment_id', $validated['compartment_id'])
            ->first();
        if (!$compartment || (string) $compartment->rack_id !== (string) $validated['rack_id']) {
            return back()->withErrors([
                'compartment_id' => 'Selected compartment does not belong to the selected rack.',
            ]);
        }

        if (!(bool) $compartment->is_active || (string) $compartment->compartment_status !== 'open') {
            return back()->withErrors([
                'compartment_id' => 'Selected compartment is not available.',
            ]);
        }

        TenderCompartments::query()->create([
            'tender_compartment_id' => (string) Str::uuid(),
            'compartment_id' => (string) $validated['compartment_id'],
            'vendor_id' => $validated['vendor_id'],
            'bid_price' => $validated['bid_price'],
            'durations' => $validated['durations'],
            'product_description' => $validated['product_description'],
            'tender_status' => $validated['tender_status'],
            'selected_at' => $validated['selected_at'] ?? null,
            'unallocated_at' => null,
            'unallocated_by' => null,
            'unallocated_reason' => null,
        ]);

        return redirect()->route('tender_compartments.index')->with([
            'success' => 'Tender compartment created successfully',
        ]);
    }

    public function edit(TenderCompartments $tenderCompartment)
    {
        $rackId = Compartments::query()
            ->where('compartment_id', (string) $tenderCompartment->compartment_id)
            ->value('rack_id');

        return Inertia::render('racks-tenders/tender-compartments/edit', [
            'tenderCompartment' => array_merge($tenderCompartment->toArray(), [
                'rack_id' => $rackId ? (string) $rackId : null,
            ]),
            'vendors' => $this->vendorOptions(),
            'vendorLocations' => $this->vendorLocationOptions(),
            'racks' => $this->rackList(),
            'compartments' => $this->compartmentList(),
        ]);
    }

    public function update(Request $request, TenderCompartments $tenderCompartment)
    {
        $validated = $request->validate([
            'rack_id' => ['required', 'uuid', 'exists:racks,rack_id'],
            'compartment_id' => ['required', 'uuid', 'exists:compartments,compartment_id'],
            'vendor_id' => ['required', 'exists:vendors,vendor_id'],
            'bid_price' => ['required', 'numeric', 'min:0'],
            'durations' => ['required', 'integer', 'min:1'],
            'product_description' => ['required', 'string', 'max:255'],
            'tender_status' => ['required', 'in:pending,selected,paid,expired,rejected'],
            'selected_at' => ['nullable', 'date'],
        ]);

        $compartment = Compartments::query()
            ->where('compartment_id', $validated['compartment_id'])
            ->first();
        if (!$compartment || (string) $compartment->rack_id !== (string) $validated['rack_id']) {
            return back()->withErrors([
                'compartment_id' => 'Selected compartment does not belong to the selected rack.',
            ]);
        }

        if (!(bool) $compartment->is_active || (string) $compartment->compartment_status !== 'open') {
            return back()->withErrors([
                'compartment_id' => 'Selected compartment is not available.',
            ]);
        }

        $tenderCompartment->update([
            'compartment_id' => (string) $validated['compartment_id'],
            'vendor_id' => $validated['vendor_id'],
            'bid_price' => $validated['bid_price'],
            'durations' => $validated['durations'],
            'product_description' => $validated['product_description'],
            'tender_status' => $validated['tender_status'],
            'selected_at' => $validated['selected_at'] ?? null,
            'unallocated_at' => null,
            'unallocated_by' => null,
            'unallocated_reason' => null,
        ]);

        return redirect()->route('tender_compartments.index')->with([
            'success' => 'Tender compartment updated successfully',
        ]);
    }

    public function destroy(TenderCompartments $tenderCompartment)
    {
        TenderCompartments::destroy($tenderCompartment->getKey());

        return redirect()->route('tender_compartments.index')->with([
            'success' => 'Tender compartment deleted successfully',
        ]);
    }

    private function vendorOptions(): array
    {
        return Vendors::query()
            ->orderBy('vendor_name', 'asc')
            ->get(['vendor_id', 'vendor_name'])
            ->map(fn($v) => [
                'value' => (string) $v->vendor_id,
                'label' => (string) $v->vendor_name,
            ])
            ->all();
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

    private function compartmentList(): array
    {
        return Compartments::query()
            ->orderBy('label', 'asc')
            ->get([
                'compartment_id',
                'rack_id',
                'label',
                'row_index',
                'column_index',
                'compartment_status',
                'is_active',
            ])
            ->map(fn($c) => [
                'compartment_id' => (string) $c->compartment_id,
                'rack_id' => (string) $c->rack_id,
                'label' => (string) $c->label,
                'row_index' => (int) $c->row_index,
                'column_index' => (int) $c->column_index,
                'compartment_status' => (string) $c->compartment_status,
                'is_active' => (bool) $c->is_active,
            ])
            ->all();
    }
}
