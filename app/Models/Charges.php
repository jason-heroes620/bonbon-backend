<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Charges extends Model
{
    use HasUuids;

    protected $table = 'charges';
    protected $primaryKey = 'charges_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'charges_id',
        'charges_type',
        'charges_name',
        'charges_rate',
        'charges_description',
        'charges_status',
        'charges_start_date',
        'charges_end_date',
        'sort_order',
    ];

    public $timestamps = true;

    protected $casts = [
        'charges_status' => 'boolean',
    ];
}
