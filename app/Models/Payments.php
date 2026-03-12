<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Payments extends Model
{
    use HasUuids;

    protected $table = 'payments';
    protected $primaryKey = 'payment_id';
    protected $fillable = [
        'order_no',
        'payment_description',
        'payment_method',
        'payment_amount',
        'order_id',
        'transaction_id',
        'ref_no',
        'payment_date',
        'issuing_bank',
        'payment_ref',
        'bank_ref',
        'cc_name',
        'cc_number',
        'payment_status',
    ];

    public $timestamps = true;
}
