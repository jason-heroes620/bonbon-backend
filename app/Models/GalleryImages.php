<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GalleryImages extends Model
{
    use HasUuids;

    protected $table = 'gallery_images';
    protected $primaryKey = 'gallery_image_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'title',
        'caption',
        'original_path',
        'original_url',
        'thumbnail_path',
        'thumbnail_url',
        'optimized_path',
        'optimized_url',
        'mime_type',
        'image_width',
        'image_height',
        'file_size_bytes',
        'status',
        'failure_reason',
        'is_active',
        'uploaded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'image_width' => 'integer',
        'image_height' => 'integer',
        'file_size_bytes' => 'integer',
    ];

    public function taggedUsers(): HasMany
    {
        return $this->hasMany(GalleryImageUsers::class, 'gallery_image_id', 'gallery_image_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'gallery_image_users',
            'gallery_image_id',
            'user_id',
            'gallery_image_id',
            'user_id',
        )->withPivot('gallery_image_user_id');
    }
}
