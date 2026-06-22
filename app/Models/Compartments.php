<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compartments extends Model
{
    protected $table = 'compartments';
    protected $primaryKey = 'compartment_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'compartment_id',
        'rack_id',
        'label',
        'row_index',
        'column_index',
        'size_dimensions',
        'min_price',
        'min_month',
        'compartment_status',
        'is_active',
    ];

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Racks::class);
    }

    public function tenders(): HasMany
    {
        return $this->hasMany(Tenders::class);
    }

    public function currentSelectedTender()
    {
        return $this->tenders()->where('status', 'selected')->first();
    }
}
