<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImages extends Model
{
    use HasUuids;

    protected $table = 'product_images';
    protected $primaryKey = 'product_image_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'product_id',
        'image_url',
        'image_path',
        'mobile_image_url',
        'mobile_image_path',
        'is_active',
        'is_primary',
        'image_width',
        'image_height',
        'file_size_bytes',
        'mobile_file_size_bytes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'image_width' => 'integer',
        'image_height' => 'integer',
        'file_size_bytes' => 'integer',
        'mobile_file_size_bytes' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, 'product_id');
    }
}
