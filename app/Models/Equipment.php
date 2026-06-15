<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'current_rig_id',
        'name',
        'marque',
        'serial_number',
        'photo',
        'hours_of_operation',
        'status',
    ];

    protected $table = 'equipments';

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo
            ? asset($this->photo)
            : null;
    }

    public function rig(): BelongsTo
    {
        return $this->belongsTo(Rig::class, 'current_rig_id');
    }

    public function scopeForRig($query, int $rigId)
    {
        return $query->where('current_rig_id', $rigId);
    }


}
