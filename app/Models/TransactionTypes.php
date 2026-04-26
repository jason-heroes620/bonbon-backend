<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionTypes extends Model
{
    protected $table = 'transaction_types';
    protected $primaryKey = 'id';
    protected $fillable = [
        'transaction_type',
        'transaction_name',
        'credit_amount',
        'effective_date',
        'expire_date',
        'is_active',
    ];
    public $timestamps = true;

    protected $casts = [
        'credit_amount' => 'integer',
        'effective_date' => 'date',
        'expire_date' => 'date',
        'is_active' => 'boolean',
    ];
}
