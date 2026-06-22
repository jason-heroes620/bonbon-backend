<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Racks extends Model
{
    use HasFactory, HasUuids;
    protected $table = 'racks';
    protected $primaryKey = 'rack_id';

    protected $fillable = [
        'vendor_location_id',
        'rack_name',
        'rack_type',
        'rack_capacity',
        'rack_rows',
        'rack_columns',
        'rack_status',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(VendorLocation::class, 'vendor_location_id');
    }

    public function compartments(): HasMany
    {
        return $this->hasMany(Compartments::class);
    }
}
