<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorLocation extends Model
{
    protected $table = 'vendor_locations';

    protected $fillable = [
        'vendor_id',
        'location_name',
        'address',
        'latitude',
        'longitude',
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
}
