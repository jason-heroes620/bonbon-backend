<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCategories extends Model
{
    protected $table = 'event_categories';
    protected $primaryKey = 'event_category_id';
    protected $fillable = [
        'event_id',
        'category_id',
    ];
    public $timestamps = true;
}
