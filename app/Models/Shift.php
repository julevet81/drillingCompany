<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Shift extends Model
{
    protected $fillable = ['date', 'periode', 'rig_id'];

    protected $casts = ['date' => 'date'];

    public function rig(): BelongsTo
    {
        return $this->belongsTo(Rig::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_shifts')
            ->withPivot(['function', 'status']);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForRig($query, int $rigId)
    {
        return $query->where('rig_id', $rigId);
    }
}
