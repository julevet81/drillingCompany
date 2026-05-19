<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Equipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'current_rig_id',
        'name',
        'marque',
        'serial_number',
    ];

    public function rig(): BelongsTo
    {
        return $this->belongsTo(Rig::class, 'current_rig_id');
    }

    public function scopeForRig($query, int $rigId)
    {
        return $query->where('current_rig_id', $rigId);
    }
}
