<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditTransactions extends Model
{
    protected $table = 'credit_transactions';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_id',
        'credit_amount',
        'transaction_type',
        'reference_id',
        'reference_type',
        'transaction_description',
    ];
    public $timestamps = true;
}
