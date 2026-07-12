<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgeGroups extends Model
{
    protected $table = 'age_groups';
    protected $primaryKey = 'age_group_id';
    protected $keyType = 'integer';
    public $incrementing = false;
    protected $fillable = [
        'age_group',
        'age_group_description',
        'min_age',
        'max_age',
    ];
}
