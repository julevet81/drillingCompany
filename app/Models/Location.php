<?php

namespace App\Models;

use App\Models\Rig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'state', 'latitude', 'longitude'];

    protected $casts = [
        'latitude'  => 'decimal:6',
        'longitude' => 'decimal:6',
    ];

    public function rigs(): HasMany
    {
        return $this->hasMany(Rig::class);
    }
}
