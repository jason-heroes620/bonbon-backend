<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class VendorLocation extends Model
{
    protected $table = 'vendor_locations';

    protected $fillable = [
        'vendor_id',
        'contact_no',
        'location_name',
        'address',
        'latitude',
        'longitude',
        'location',
        'place_id',
        'is_primary',
    ];

    protected $casts = [
        'longitude' => 'float', // or 'double'
        'latitude' => 'float',  // or 'double'
        'is_primary' => 'boolean',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendors::class, 'vendor_id', 'vendor_id');
    }

    public function setLocationAttribute($value)
    {
        $this->attributes['location'] = DB::raw("ST_PointFromText('POINT({$value['lat']} {$value['lng']})', 4326)");
    }
}
