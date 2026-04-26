<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserMemberships extends Model
{
    use HasUuids;
    protected $table = 'user_memberships';
    protected $primaryKey = 'user_membership_id';

    protected $fillable = [
        'user_id',
        'membership_id',
        'membership_start_date',
        'membership_end_date',
        'membership_status',
        'inactive_reason',
        'auto_renew',
    ];

    public $timestamps = true;

    protected $casts = [
        'is_active' => 'boolean',
        'auto_renew' => 'boolean',
    ];

    public function membership()
    {
        return $this->hasOne(Memberships::class, 'membership_id', 'membership_id');
    }
}
