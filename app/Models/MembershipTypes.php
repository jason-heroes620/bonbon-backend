<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipTypes extends Model
{
    use HasUuids;

    protected $table = 'membership_types';
    protected $primaryKey = 'membership_type_id';
    protected $fillable = [
        'membership_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(Memberships::class, 'membership_type_id', 'membership_type_id');
    }
}
