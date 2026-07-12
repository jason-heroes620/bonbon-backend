<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Racks;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RacksController extends Controller
{
    public function vendorRacks(Request $request, string $vendor_id)
    {
        $validated = $request->validate([
            'vendor_location_id' => ['nullable', 'integer'],
        ]);

        $query = Racks::query()
            ->join('vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->where('vendor_locations.vendor_id', $vendor_id)
            ->where('racks.rack_status', 'active')
            ->orderBy('vendor_locations.location_name')
            ->orderBy('racks.rack_name')
            ->select([
                'racks.rack_id',
                'racks.vendor_location_id',
                'racks.rack_name',
                'racks.rack_type',
                'racks.rack_capacity',
                'racks.rack_rows',
                'racks.rack_columns',
                'racks.rack_status',
                'vendor_locations.location_name',
            ]);

        if (!empty($validated['vendor_location_id'])) {
            $query->where('racks.vendor_location_id', (int) $validated['vendor_location_id']);
        }

        $locations = $query->get()
            ->groupBy('vendor_location_id')
            ->map(function ($racks) use ($vendor_id) {
                $firstRack = $racks->first();

                return [
                    'vendor_id' => $vendor_id,
                    'vendor_location_id' => (int) $firstRack->vendor_location_id,
                    'vendor_location_name' => (string) $firstRack->location_name,
                    'racks' => $racks->map(fn($rack) => [
                        'rack_id' => (string) $rack->rack_id,
                        'rack_name' => (string) $rack->rack_name,
                        'rack_type' => $rack->rack_type ? (string) $rack->rack_type : null,
                        'rack_capacity' => $rack->rack_capacity !== null ? (int) $rack->rack_capacity : null,
                        'rack_rows' => $rack->rack_rows !== null ? (int) $rack->rack_rows : null,
                        'rack_columns' => $rack->rack_columns !== null ? (int) $rack->rack_columns : null,
                        'rack_status' => (string) $rack->rack_status,
                    ])->values(),
                ];
            })
            ->values();
        Log::info($locations);
        return response()->json([
            'data' => $locations,
        ]);
    }

    public function rackStockProducts(string $rack_id)
    {
        $today = Carbon::today()->toDateString();

        $rack = Racks::query()
            ->join('vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->where('racks.rack_id', $rack_id)
            ->where('racks.rack_status', 'active')
            ->select([
                'racks.rack_id',
                'racks.vendor_location_id',
                'racks.rack_name',
                'racks.rack_type',
                'racks.rack_status',
                'vendor_locations.vendor_id',
                'vendor_locations.location_name',
            ])
            ->first();

        if (!$rack) {
            return response()->json([
                'message' => 'Rack not found or inactive.',
            ], 404);
        }

        $rows = Racks::query()
            ->join('compartments', 'compartments.rack_id', '=', 'racks.rack_id', 'inner', false)
            ->join('tender_compartments', 'tender_compartments.compartment_id', '=', 'compartments.compartment_id', 'inner', false)
            ->leftJoin('vendors as contract_vendors', 'tender_compartments.vendor_id', '=', 'contract_vendors.vendor_id', 'left', false)
            ->join('compartment_stocks', 'compartment_stocks.tender_compartment_id', '=', 'tender_compartments.tender_compartment_id', 'inner', false)
            ->join('compartment_stock_products', 'compartment_stock_products.compartment_stock_id', '=', 'compartment_stocks.compartment_stock_id', 'inner', false)
            ->join('products', 'products.product_id', '=', 'compartment_stock_products.product_id', 'inner', false)
            ->where('racks.rack_id', $rack_id)
            ->where('racks.rack_status', 'active')
            ->where('compartment_stocks.status', 'completed')
            ->whereIn('tender_compartments.tender_status', ['selected', 'paid'])
            ->whereNotNull('tender_compartments.tender_end_date')
            ->whereDate('tender_compartments.tender_end_date', '>=', $today)
            ->orderBy('compartments.row_index')
            ->orderBy('compartments.column_index')
            ->orderBy('compartment_stocks.created_at')
            ->orderBy('products.product_name')
            ->get([
                'compartments.compartment_id',
                'compartments.label as compartment_name',
                'compartments.row_index',
                'compartments.column_index',
                'tender_compartments.tender_compartment_id',
                'tender_compartments.tender_status',
                'tender_compartments.tender_start_date',
                'tender_compartments.tender_end_date',
                'contract_vendors.vendor_name as contract_vendor_name',
                'compartment_stocks.compartment_stock_id',
                'compartment_stocks.status as compartment_stock_status',
                'compartment_stock_products.compartment_stock_product_id',
                'compartment_stock_products.expiry_date',
                'compartment_stock_products.quantity',
                'products.product_id',
                'products.product_name',
            ]);

        $compartments = $rows
            ->groupBy(fn($row) => (string) $row->compartment_id)
            ->map(function ($compartmentRows) {
                $firstCompartmentRow = $compartmentRows->first();

                return [
                    'compartment_id' => (string) $firstCompartmentRow->compartment_id,
                    'compartment_name' => (string) $firstCompartmentRow->compartment_name,
                    'row_index' => $firstCompartmentRow->row_index !== null ? (int) $firstCompartmentRow->row_index : null,
                    'column_index' => $firstCompartmentRow->column_index !== null ? (int) $firstCompartmentRow->column_index : null,
                    'tender_compartment_id' => (string) $firstCompartmentRow->tender_compartment_id,
                    'tender_status' => (string) $firstCompartmentRow->tender_status,
                    'vendor_name' => $firstCompartmentRow->contract_vendor_name ? (string) $firstCompartmentRow->contract_vendor_name : null,
                    'tender_start_date' => $firstCompartmentRow->tender_start_date ? (string) $firstCompartmentRow->tender_start_date : null,
                    'contract_expiry' => $firstCompartmentRow->tender_end_date ? (string) $firstCompartmentRow->tender_end_date : null,
                    'stocks' => $compartmentRows
                        ->groupBy(fn($row) => (string) $row->compartment_stock_id)
                        ->map(function ($stockRows) {
                            $firstStockRow = $stockRows->first();

                            return [
                                'compartment_stock_id' => (string) $firstStockRow->compartment_stock_id,
                                'status' => (string) $firstStockRow->compartment_stock_status,
                                'products' => $stockRows->map(fn($productRow) => [
                                    'compartment_stock_product_id' => (string) $productRow->compartment_stock_product_id,
                                    'product_id' => (string) $productRow->product_id,
                                    'product_name' => (string) $productRow->product_name,
                                    'expiry_date' => $productRow->expiry_date ? (string) $productRow->expiry_date : null,
                                    'quantity' => (int) $productRow->quantity,
                                ])->values(),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'rack_id' => (string) $rack->rack_id,
                'rack_name' => (string) $rack->rack_name,
                'rack_type' => $rack->rack_type ? (string) $rack->rack_type : null,
                'rack_status' => (string) $rack->rack_status,
                'vendor_id' => (string) $rack->vendor_id,
                'vendor_location_id' => (int) $rack->vendor_location_id,
                'vendor_location_name' => (string) $rack->location_name,
                'compartments' => $compartments,
            ],
        ]);
    }

    public function vendorPreparedCompartmentStocks(string $vendor_id)
    {
        $rows = Racks::query()
            ->join('vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->join('compartments', 'compartments.rack_id', '=', 'racks.rack_id', 'inner', false)
            ->join('tender_compartments', 'tender_compartments.compartment_id', '=', 'compartments.compartment_id', 'inner', false)
            ->join('compartment_stocks', 'compartment_stocks.tender_compartment_id', '=', 'tender_compartments.tender_compartment_id', 'inner', false)
            ->join('compartment_stock_products', 'compartment_stock_products.compartment_stock_id', '=', 'compartment_stocks.compartment_stock_id', 'inner', false)
            ->join('products', 'products.product_id', '=', 'compartment_stock_products.product_id', 'inner', false)
            ->where('tender_compartments.vendor_id', $vendor_id)
            ->where('compartment_stocks.status', 'prepared')
            ->orderBy('vendor_locations.location_name')
            ->orderBy('racks.rack_name')
            ->orderBy('compartments.row_index')
            ->orderBy('compartments.column_index')
            ->orderBy('products.product_name')
            ->get([
                'vendor_locations.location_name as vendor_location_name',
                'racks.rack_id',
                'racks.rack_name',
                'compartments.compartment_id',
                'compartments.label as compartment_name',
                'tender_compartments.tender_compartment_id',
                'compartment_stocks.compartment_stock_id',
                'compartment_stocks.status',
                'compartment_stock_products.compartment_stock_product_id',
                'compartment_stock_products.quantity',
                'compartment_stock_products.expiry_date',
                'products.product_id',
                'products.product_name',
            ])
            ->map(fn($row) => [
                'vendor_id' => $vendor_id,
                'vendor_location_name' => (string) $row->vendor_location_name,
                'rack_id' => (string) $row->rack_id,
                'rack_name' => (string) $row->rack_name,
                'compartment_id' => (string) $row->compartment_id,
                'compartment_name' => (string) $row->compartment_name,
                'tender_compartment_id' => (string) $row->tender_compartment_id,
                'compartment_stock_id' => (string) $row->compartment_stock_id,
                'compartment_stock_product_id' => (string) $row->compartment_stock_product_id,
                'quantity' => $row->quantity !== null ? (int) $row->quantity : null,
                'expiry_date' => $row->expiry_date ? (string) $row->expiry_date : null,
                'product_id' => (string) $row->product_id,
                'product_name' => (string) $row->product_name,
                'status' => (string) $row->status,
            ])
            ->values();

        return response()->json([
            'data' => $rows,
        ]);
    }
}
