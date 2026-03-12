<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Taxes extends Model
{
    use HasUuids;

    protected $table = 'taxes';
    protected $primaryKey = 'tax_rate_id';
    protected $fillable = [
        'tax_name',
        'tax_rate',
        'is_active',
    ];

    public $timestamps = true;

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
