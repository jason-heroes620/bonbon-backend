<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Vendors extends Model
{
    use HasUuids;

    protected $table = 'vendors';
    protected $primaryKey = 'vendor_id';

    protected $fillable = [
        'vendor_name',
        'user_id',
        'email',
        'contact_no',
        'contact_person',
        'business_registration_number',
        'company_profile',
        'profile_picture',
        'is_active',
    ];
}
