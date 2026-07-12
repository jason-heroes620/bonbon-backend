<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasUuids;

    protected $table = 'carts';
    protected $primaryKey = 'cart_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'cart_status',
        'currency_code',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id', 'cart_id');
    }
}

