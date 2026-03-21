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
        'first_name',
        'last_name',
        'business_registration_number',
        'company_profile',
        'profile_picture',
        'website',
        'social_medias',
        'is_active',
    ];



    public function locations()
    {
        return $this->hasMany(VendorLocation::class, 'vendor_id', 'vendor_id');
    }

    public function voucher()
    {
        return $this->hasMany(Vouchers::class, 'vendor_id', 'vendor_id');
    }

    public function categories()
    {
        return $this->hasMany(VendorCategories::class, 'vendor_id', 'vendor_id');
    }
}
