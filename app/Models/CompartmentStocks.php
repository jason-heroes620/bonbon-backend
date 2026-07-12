<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CompartmentStocks extends Model
{
    use HasUuids;

    protected $table = 'compartment_stocks';
    protected $primaryKey = 'compartment_stock_id';
    protected $keyType = 'uuid';
    public $incrementing = false;
    protected $fillable = [
        'tender_compartment_id',
        'status',
        'confirmed_received_at',
        'confirmed_received_by_user_id',
        'confirmation_source',
    ];
}
