<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenders extends Model
{
    protected $table = 'tenders';
    protected $primaryKey = 'tender_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'tender_id',
        'rack_id',
        'tender_status'
    ];
}
