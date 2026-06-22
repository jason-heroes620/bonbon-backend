<?php

namespace App\Http\Controllers;

use App\Models\Compartments;
use App\Models\Racks;
use App\Models\VendorLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RacksController extends Controller
{
    public function index()
    {
        return Inertia::render('racks-tenders/racks/racks');
    }

    public function showAll(Request $request)
    {
        $query = Racks::query()
            ->leftJoin('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id')
            ->leftJoin('vendors as vendors', 'vendor_locations.vendor_id', '=', 'vendors.vendor_id')
            ->select([
                'racks.*',
                'vendor_locations.location_name as vendor_location_name',
                'vendors.vendor_name',
            ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('racks.rack_name', 'like', "%{$search}%")
                    ->orWhere('racks.rack_type', 'like', "%{$search}%")
                    ->orWhere('vendors.vendor_name', 'like', "%{$search}%")
                    ->orWhere('vendor_locations.location_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('filters')) {
            $filters = $request->input('filters', []);
            if (is_array($filters) && array_key_exists('rack_status', $filters) && $filters['rack_status'] !== null && $filters['rack_status'] !== '') {
                $query->where('racks.rack_status', $filters['rack_status']);
            }
        }

        $allowedSortFields = [
            'rack_name',
            'rack_type',
            'rack_rows',
            'rack_columns',
            'rack_status',
            'vendor_name',
            'vendor_location_name',
            'created_at',
        ];

        if ($request->has('sort')) {
            $field = (string) ($request->input('sort.field') ?? '');
            $direction = strtolower((string) ($request->input('sort.direction') ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            if (in_array($field, $allowedSortFields, true)) {
                if ($field === 'vendor_name') {
                    $query->orderBy('vendors.vendor_name', $direction);
                } elseif ($field === 'vendor_location_name') {
                    $query->orderBy('vendor_locations.location_name', $direction);
                } else {
                    $query->orderBy("racks.{$field}", $direction);
                }
            } else {
                $query->orderBy('racks.created_at', 'desc');
            }
        } else {
            $query->orderBy('racks.created_at', 'desc');
        }

        $perPage = (int) ($request->input('per_page') ?? 10);
        $perPage = max(1, min($perPage, 100));
        $racks = $query->paginate($perPage);

        return response()->json([
            'data' => $racks->items(),
            'meta' => [
                'current_page' => $racks->currentPage(),
                'last_page' => $racks->lastPage(),
                'per_page' => $racks->perPage(),
                'total' => $racks->total(),
                'from' => $racks->firstItem(),
                'to' => $racks->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('racks-tenders/racks/create', [
            'vendorLocations' => $this->vendorLocationOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_location_id' => ['required', 'integer', 'exists:vendor_locations,id'],
            'rack_name' => ['required', 'string', 'max:255'],
            'rack_type' => ['nullable', 'string', 'max:255'],
            'rack_capacity' => ['nullable', 'string', 'max:255'],
            'rack_rows' => ['required', 'integer', 'min:1'],
            'rack_columns' => ['required', 'integer', 'min:1'],
            'rack_status' => ['required', 'in:active,inactive'],
        ]);

        $rack = DB::transaction(function () use ($validated) {
            $rack = Racks::query()->create([
                'rack_id' => (string) Str::uuid(),
                'vendor_location_id' => (string) $validated['vendor_location_id'],
                'rack_name' => $validated['rack_name'],
                'rack_type' => $validated['rack_type'] ?? null,
                'rack_capacity' => $validated['rack_capacity'] ?? null,
                'rack_rows' => (string) $validated['rack_rows'],
                'rack_columns' => (string) $validated['rack_columns'],
                'rack_status' => $validated['rack_status'],
            ]);

            $this->syncCompartmentsForRack($rack);

            return $rack;
        });

        return redirect()->route('racks.compartments.edit', $rack->rack_id)->with([
            'success' => 'Rack created successfully',
        ]);
    }

    public function edit(Racks $rack)
    {
        return Inertia::render('racks-tenders/racks/edit', [
            'rack' => $rack,
            'vendorLocations' => $this->vendorLocationOptions(),
        ]);
    }

    public function update(Request $request, Racks $rack)
    {
        $validated = $request->validate([
            'vendor_location_id' => ['required', 'integer', 'exists:vendor_locations,id'],
            'rack_name' => ['required', 'string', 'max:255'],
            'rack_type' => ['nullable', 'string', 'max:255'],
            'rack_capacity' => ['nullable', 'string', 'max:255'],
            'rack_rows' => ['required', 'integer', 'min:1'],
            'rack_columns' => ['required', 'integer', 'min:1'],
            'rack_status' => ['required', 'in:active,inactive'],
        ]);

        DB::transaction(function () use ($rack, $validated) {
            $rack->update([
                'vendor_location_id' => (string) $validated['vendor_location_id'],
                'rack_name' => $validated['rack_name'],
                'rack_type' => $validated['rack_type'] ?? null,
                'rack_capacity' => $validated['rack_capacity'] ?? null,
                'rack_rows' => (string) $validated['rack_rows'],
                'rack_columns' => (string) $validated['rack_columns'],
                'rack_status' => $validated['rack_status'],
            ]);

            $this->syncCompartmentsForRack($rack);
        });

        return redirect()->route('racks.compartments.edit', $rack->rack_id)->with([
            'success' => 'Rack updated successfully',
        ]);
    }

    public function destroy(Racks $rack)
    {
        $hasCompartments = DB::table('compartments')
            ->where('rack_id', $rack->rack_id)
            ->exists();

        if ($hasCompartments) {
            return redirect()->route('racks.index')->with([
                'error' => 'Rack has compartments and cannot be deleted.',
            ]);
        }

        Racks::destroy($rack->getKey());

        return redirect()->route('racks.index')->with([
            'success' => 'Rack deleted successfully',
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

    private function syncCompartmentsForRack(Racks $rack): void
    {
        $rows = max(1, (int) $rack->rack_rows);
        $cols = max(1, (int) $rack->rack_columns);

        $existing = Compartments::query()
            ->where('rack_id', $rack->rack_id)
            ->get()
            ->keyBy(fn($c) => (string) $c->row_index . '-' . (string) $c->column_index);

        for ($r = 1; $r <= $rows; $r++) {
            for ($c = 1; $c <= $cols; $c++) {
                $key = $r . '-' . $c;
                $label = $r <= 26
                    ? chr(64 + $r) . (string) $c
                    : 'R' . $r . 'C' . $c;

                $compartment = $existing->get($key);
                if ($compartment) {
                    Compartments::query()
                        ->where('compartment_id', $compartment->compartment_id)
                        ->update([
                            'is_active' => true,
                            'label' => $compartment->label ?: $label,
                        ]);
                    continue;
                }

                Compartments::query()->create([
                    'compartment_id' => (string) Str::uuid(),
                    'rack_id' => $rack->rack_id,
                    'label' => $label,
                    'row_index' => $r,
                    'column_index' => $c,
                    'size_dimensions' => null,
                    'min_price' => 0,
                    'min_month' => 6,
                    'compartment_status' => 'open',
                    'is_active' => true,
                ]);
            }
        }

        Compartments::query()
            ->where('rack_id', $rack->rack_id)
            ->where(function ($q) use ($rows, $cols) {
                $q->where('row_index', '>', $rows)
                    ->orWhere('column_index', '>', $cols);
            })
            ->update([
                'compartment_status' => 'closed',
                'is_active' => false,
            ]);
    }
}
