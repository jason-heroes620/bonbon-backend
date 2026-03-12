<?php

namespace App\Http\Controllers;

use App\Models\Categories;
use App\Models\User;
use App\Models\VendorCategories;
use App\Models\Vendors;
use App\Models\VendorLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class VendorsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('vendors/vendors');
    }

    public function showAll(Request $request)
    {
        $query = Vendors::query();

        if ($search = $request->has('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('vendor_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
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
            $query->orderBy('vendors.created_at', 'desc');
        }
        $perPage = $request->per_page ?? 10;
        $vendors = $query->paginate($perPage);

        return response()->json([
            'data' => $vendors->items(),
            'meta' => [
                'current_page' => $vendors->currentPage(),
                'last_page' => $vendors->lastPage(),
                'per_page' => $vendors->perPage(),
                'total' => $vendors->total(),
                'from' => $vendors->firstItem(),
                'to' => $vendors->lastItem(),
            ],
        ]);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Categories::select('category_id as value', 'category_name as label')
            ->where('is_active', true)->get();
        return Inertia::render('vendors/create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'vendor_name' => 'required|string|max:150',
            'email' => 'required|string|email|max:200|unique:vendors',
            'contact_no' => 'required|string|max:25',
            'contact_person' => 'required|string|max:150',
            'busines_registration_number' => 'required|string|max:100',
            'company_profile' => 'nullable|string',
            "our_services" => 'nullable|string',
            'locations' => 'nullable|array',
            'locations.*.location_name' => 'required|string',
            'locations.*.latitude' => 'required|numeric',
            'locations.*.longitude' => 'required|numeric',
        ]);

        $user = User::where('email', $request->email)->first();
        if ($request->profile_picture !== null) {
            $profile_picture = $request->profile_picture->store('profile_pictures', 'public');
        }

        if (!$user) {
            $user = User::create([
                'name' => $request->contact_person,
                'email' => $request->email,
                'password' => bcrypt('password'),
                'role' => 'vendor',
            ]);
            Log::info('User created', ['user' => $user->user_id]);
        } else {
            return redirect()->back()->with('error', 'This email is already registered');
        }

        $vendor = Vendors::firstOrCreate([
            'vendor_name' => $request->vendor_name,
            'email' => $request->email,
            'contact_no' => $request->contact_no,
            'contact_person' => $request->contact_person,
            'business_registration_number' => $request->busines_registration_number,
            'company_profile' => $request->company_profile,
            'our_services' => $request->our_services,
            'profile_picture' => $profile_picture ?? null,
            'is_active' => 'inactive',
            'user_id' => $user->user_id,
        ]);

        if ($request->has('locations')) {
            foreach ($request->locations as $location) {
                VendorLocation::create([
                    'vendor_id' => $vendor->vendor_id,
                    'location_name' => $location['location_name'],
                    'address' => $location['address'] ?? null,
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'place_id' => $location['place_id'] ?? null,
                    'is_primary' => $location['is_primary'] ?? false,
                ]);
            }
        }

        if ($request->has('categories')) {
            foreach ($request->categories as $category) {
                VendorCategories::create([
                    'vendor_id' => $vendor->vendor_id,
                    'category_id' => $category,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Vendor created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        return Inertia::render('vendors/show', [
            'vendor' => Vendors::find($request->vendor),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request)
    {
        $categories = Categories::select('category_id as value', 'category_name as label')
            ->where('is_active', true)->get();

        $vendor = Vendors::with('locations')->find($request->vendor);

        $vendor->categories = VendorCategories::where('vendor_categories.vendor_id', $vendor->vendor_id)
            ->pluck('vendor_categories.category_id')->toArray();

        if ($vendor->profile_picture !== null) {
            $vendor->profile_picture = asset('storage/' . $vendor->profile_picture);
        }

        return Inertia::render('vendors/edit', [
            'vendor' => $vendor,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'vendor_name' => 'required|string|max:150',
                'contact_no' => 'required|string|max:25',
                'contact_person' => 'required|string|max:150',
                'business_registration_number' => 'required|string|max:100',
                'company_profile' => 'nullable|string',
            ]);
            if ($validator->fails()) {
                Log::error('Update vendor validation failed', ['errors' => $validator->errors()->toArray()]);
                return redirect()->back()->withErrors($validator)->withInput();
            }
            Log::info('Update before vendor', ['vendor' => $request->all()]);

            $updateData = [
                'vendor_name' => $request->vendor_name,
                'contact_no' => $request->contact_no,
                'contact_person' => $request->contact_person,
                'business_registration_number' => $request->business_registration_number,
                'company_profile' => $request->company_profile,
                'our_services' => $request->our_services,
            ];

            if ($request->hasFile('profile_picture')) {
                $updateData['profile_picture'] = $request->file("profile_picture")->store('profile_pictures', 'public');
            }

            Log::info('Update vendor', ['vendor' => $request->all()]);
            Vendors::where('vendor_id', $request->vendor)->update($updateData);

            if ($request->has('locations')) {
                VendorLocation::where('vendor_id', $request->vendor)->delete();
                foreach ($request->locations as $location) {
                    VendorLocation::create([
                        'vendor_id' => $request->vendor,
                        'location_name' => $location['location_name'],
                        'address' => $location['address'] ?? null,
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude'],
                        'place_id' => $location['place_id'] ?? null,
                        'is_primary' => $location['is_primary'] ?? false,
                    ]);
                }
            }

            if ($request->has('categories')) {
                VendorCategories::where('vendor_id', $request->vendor)->delete();
                foreach ($request->categories as $category) {
                    VendorCategories::create([
                        'vendor_id' => $request->vendor,
                        'category_id' => $category,
                    ]);
                }
            }

            return redirect()->back();
        } catch (\Exception $e) {
            Log::error('Update vendor failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update vendor');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vendors $vendors)
    {
        $vendors->update([
            'is_active' => false,
        ]);

        return redirect()->route('vendors.index');
    }

    /**
     * Get vendor list.
     */
    public function getVendorList()
    {
        Log::info('Get vendor list');
        $vendors = Vendors::select("vendor_id", "vendor_name")
            ->where('is_active', 'active')
            ->orderBy('vendor_name', 'asc')
            ->get();
        return response()->json($vendors);
    }
}
