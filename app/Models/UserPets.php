<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPets extends Model
{
    protected $table = 'user_pets';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'pet_name',
        'pet_type',
        'pet_breed',
        'pet_birth_date',
        'medical_notes',
        'allergy_notes',
        'pet_image',
    ];

    public $timestamps = true;

    protected $hidden = ['created_at', 'updated_at'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
