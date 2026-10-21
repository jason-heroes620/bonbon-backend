<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryImageUsers extends Model
{
    use HasUuids;

    protected $table = 'gallery_image_users';
    protected $primaryKey = 'gallery_image_user_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'gallery_image_id',
        'user_id',
        'email',
        'tagged_by',
    ];

    public function image(): BelongsTo
    {
        return $this->belongsTo(GalleryImages::class, 'gallery_image_id', 'gallery_image_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
