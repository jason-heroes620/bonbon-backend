<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderCharges extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_charges';
    protected $primaryKey = 'order_charge_id';
    protected $keyType = 'uuid';
    public $incrementing = false;
    protected $fillable = [
        'order_id',
        'charge_id',
        'charge_name',
        'charge_type',
        'charge_rate',
        'charge_amount',
        'sort_order',
    ];
}
