<?php

namespace App\Http\Controllers;

use App\Models\Vouchers;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VouchersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('vouchers/vouchers');
    }

    public function showAll(Request $request)
    {
        $query = Vouchers::query();
        $query->with('vendor');

        if ($search = $request->has('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('voucher_name', 'like', "%{$search}%")
                  ->orWhere('vendor_name', 'like', "%{$search}%");
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
            $query->orderBy('vouchers.created_at', 'desc');
        }
        $perPage = $request->per_page ?? 10;
        $vouchers = $query->paginate($perPage);

        return response()->json([
            'data' => $vouchers->items(),
            'meta' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
                'from' => $vouchers->firstItem(),
                'to' => $vouchers->lastItem(),
            ],
        ]);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('vouchers/create');
    }
}
