<?php

namespace App\Models;

use App\Models\MaterialLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RigMaterial extends Model
{
    public $timestamps = false;

    protected $fillable = ['rig_id', 'material_type_id', 'quantity', 'capacity'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'capacity' => 'decimal:2',
    ];

    public function rig(): BelongsTo
    {
        return $this->belongsTo(Rig::class);
    }

    public function materialType(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MaterialLog::class);
    }

    public function getFilledPercentageAttribute(): float
    {
        if (!$this->capacity || $this->capacity == 0) return 0;
        return min(100, round(($this->quantity / $this->capacity) * 100, 2));
    }

    public function isLow(float $threshold = 20.0): bool
    {
        return $this->filled_percentage < $threshold;
    }
}
