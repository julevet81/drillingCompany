<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialType extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'unit'];

    // Common defaults: Diesel Fuel (L), Bentonite (kg), Barite (kg), Cement (kg)
    public const DEFAULTS = [
        ['name' => 'Diesel Fuel', 'unit' => 'L'],
        ['name' => 'Bentonite',   'unit' => 'kg'],
        ['name' => 'Barite',      'unit' => 'kg'],
        ['name' => 'Cement',      'unit' => 'kg'],
    ];

    public function rigMaterials(): HasMany
    {
        return $this->hasMany(RigMaterial::class);
    }
}
