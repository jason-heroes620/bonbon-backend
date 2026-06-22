<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenderCompartments extends Model
{
    use HasUuids;

    protected $table = 'tender_compartments';
    protected $primaryKey = 'tender_compartment_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'tender_compartment_id',
        'compartment_id',
        'vendor_id',
        'bid_price',
        'durations',
        'tender_status',
        'selected_at',
        'unallocated_at',
        'unallocated_by',
        'unallocated_reason',
        'tender_start_date',
        'tender_end_date',
        'product_description',
    ];

    public $timestamps = true;
}
