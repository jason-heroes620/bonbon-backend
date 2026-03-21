<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EvCategories extends Model
{
    use HasUuids;

    protected $table = 'ev_categories';
    protected $primaryKey = 'category_id';
    protected $fillable = [
        'category_name',
        'is_active',
    ];
    public $timestamps = true;

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
