<?php

namespace App\Http\Controllers;

use App\Models\Categories;
use App\Models\User;
use App\Models\VendorCategories;
use App\Models\Vendors;
use App\Models\VendorLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
        $query = Vendors::query()
            ->select('vendor_id', 'vendor_name', 'email', 'contact_no', 'first_name', 'last_name', 'profile_picture', 'is_active');

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
        $profile_picture = null;
        $profile_picture_path = null;
        $validator = Validator::make($request->all(), [
            'vendor_name' => 'required|string|max:150',
            'email' => 'required|string|email|max:200|unique:vendors',
            'contact_no' => 'required|string|max:25',
            'first_name' => 'required|string|max:150',
            'last_name' => 'required|string|max:150',
            'business_registration_number' => 'required|string|max:100',
            'company_profile' => 'nullable|string',
            "our_services" => 'nullable|string',
            'website' => 'nullable|string|max:200',
            'social_medias' => 'nullable',
            'social_medias.facebook' => 'nullable|string|max:200',
            'social_medias.instagram' => 'nullable|string|max:200',
            'social_medias.youtube' => 'nullable|string|max:200',
            'social_medias.tiktok' => 'nullable|string|max:200',
            'social_medias.xiaohungshu' => 'nullable|string|max:200',
            'locations' => 'nullable|array',
            'locations.*.location_name' => 'required|string',
            'locations.*.latitude' => 'required|numeric',
            'locations.*.longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        if (User::where('email', $request->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered.',
            ]);
        }

        try {
            if ($request->profile_picture !== null) {
                $profile_picture = $request->profile_picture->store('profile_pictures', 'public');
                $profile_picture_path = $profile_picture;
            }

            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => bcrypt('password'),
                'role' => 'vendor',
                'profile_picture' => $profile_picture_path,
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating user: ' . $e->getMessage());
            throw ValidationException::withMessages([
                'email' => 'An error occurred while creating the vendor account.',
            ]);
        }

        $vendor = Vendors::firstOrCreate([
            'vendor_name' => $request->vendor_name,
            'email' => $request->email,
            'contact_no' => $request->contact_no,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'business_registration_number' => $request->business_registration_number,
            'company_profile' => $request->company_profile,
            'our_services' => $request->our_services,
            'profile_picture' => $profile_picture_path ?? null,
            'website' => $request->website,
            'social_medias' => $this->encodeSocialMedias($request->input('social_medias')),
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
                    'location' => ['lat' => $location['latitude'], 'lng' => $location['longitude']],
                    'place_id' => $location['place_id'] ?? null,
                    'is_primary' => $location['is_primary'] ?? false,
                    'contact_no' => $location['contact_no'] ?? null,
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
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request)
    {
        $categories = Categories::select('category_id as value', 'category_name as label')
            ->where('is_active', true)->get();

        $vendor = Vendors::with('locations:id,vendor_id,location_name,address,latitude,longitude,place_id,contact_no')->find($request->vendor);

        $vendor->categories = VendorCategories::where('vendor_categories.vendor_id', $vendor->vendor_id)
            ->pluck('vendor_categories.category_id')->toArray();

        if (is_string($vendor->social_medias) && $vendor->social_medias !== '') {
            $decoded = json_decode($vendor->social_medias, true);
            if (is_array($decoded)) {
                $vendor->social_medias = $decoded;
            }
        }

        if ($vendor->profile_picture !== null) {
            $vendor->profile_picture = Storage::url($vendor->profile_picture);
        }
        Log::info($vendor);
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
                'first_name' => 'required|string|max:150',
                'last_name' => 'required|string|max:150',
                'business_registration_number' => 'required|string|max:100',
                'company_profile' => 'nullable|string',
                "our_services" => 'nullable|string',
                'website' => 'nullable|string|max:200',
                'social_medias' => 'nullable',
                'social_medias.facebook' => 'nullable|string|max:200',
                'social_medias.instagram' => 'nullable|string|max:200',
                'social_medias.youtube' => 'nullable|string|max:200',
                'social_medias.tiktok' => 'nullable|string|max:200',
                'social_medias.xiaohungshu' => 'nullable|string|max:200',
                'locations' => 'nullable|array',
                'locations.*.location_name' => 'required|string',
                'locations.*.latitude' => 'required|numeric',
                'locations.*.longitude' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                Log::error('Update vendor validation failed', ['errors' => $validator->errors()->toArray()]);
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $updateData = [
                'vendor_name' => $request->vendor_name,
                'contact_no' => $request->contact_no,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'business_registration_number' => $request->business_registration_number,
                'company_profile' => $request->company_profile,
                'our_services' => $request->our_services,
                'website' => $request->website,
                'is_active' => $request->is_active ?? 'inactive',
            ];

            if ($request->hasFile('profile_picture')) {
                $profile_picture = $request->file("profile_picture")->store('profile_pictures', 'public');
                $updateData['profile_picture'] = $profile_picture;
            }

            $vendor = Vendors::find($request->vendor);
            if ($vendor) {
                $updateData['social_medias'] = $this->mergeSocialMedias($vendor->social_medias, $request->input('social_medias'));
            } else {
                $updateData['social_medias'] = $this->encodeSocialMedias($request->input('social_medias'));
            }

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
                        'location' => ['lat' => $location['latitude'], 'lng' => $location['longitude']],
                        'place_id' => $location['place_id'] ?? null,
                        'is_primary' => $location['is_primary'] ?? false,
                        'contact_no' => $location['contact_no'] ?? null,
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

            return redirect()->back()->with('success', 'Vendor updated successfully');
        } catch (\Exception $e) {
            Log::error('Update vendor failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update vendor');
        }
    }

    private function encodeSocialMedias($value): ?string
    {
        $allowedKeys = ['facebook', 'instagram', 'youtube', 'tiktok', 'xiaohungshu'];

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            $value = [];
        }

        $result = [];
        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $value)) {
                continue;
            }
            $v = is_string($value[$key]) ? trim($value[$key]) : '';
            if ($v === '') {
                continue;
            }
            $result[$key] = $v;
        }

        if (empty($result)) {
            return json_encode((object) []);
        }

        return json_encode($result);
    }

    private function mergeSocialMedias($existing, $incoming): ?string
    {
        $allowedKeys = ['facebook', 'instagram', 'youtube', 'tiktok', 'xiaohungshu'];

        $existingArr = [];
        if (is_string($existing) && $existing !== '') {
            $decoded = json_decode($existing, true);
            $existingArr = is_array($decoded) ? $decoded : [];
        } elseif (is_array($existing)) {
            $existingArr = $existing;
        }

        if (is_string($incoming)) {
            $decoded = json_decode($incoming, true);
            $incoming = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($incoming)) {
            $incoming = [];
        }

        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $incoming)) {
                continue;
            }

            $v = is_string($incoming[$key]) ? trim($incoming[$key]) : '';
            if ($v === '') {
                unset($existingArr[$key]);
                continue;
            }
            $existingArr[$key] = $v;
        }

        if (empty($existingArr)) {
            return json_encode((object) []);
        }

        return json_encode($existingArr);
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
        $vendors = Vendors::select("vendor_id", "vendor_name")
            ->where('is_active', 'active')
            ->orderBy('vendor_name', 'asc')
            ->get();
        return response()->json($vendors);
    }
}
