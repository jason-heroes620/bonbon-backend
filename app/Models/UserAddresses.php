<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserAddresses extends Model
{
    use HasUuids;

    protected $table = 'user_addresses';
    protected $primaryKey = 'user_address_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $casts = [
        'address' => 'array',
        'is_primary' => 'boolean',
    ];
    protected $fillable = [
        'user_id',
        'address',
        'is_primary',
    ];
}
