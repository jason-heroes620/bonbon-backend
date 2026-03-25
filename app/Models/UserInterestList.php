<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserInterestList extends Model
{
    use HasUuids;

    protected $table = 'user_interest_lists';
    protected $primaryKey = 'user_interest_list_id';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'contact_no',
        'referral_code',
    ];

    public $timestamps = true;
}
