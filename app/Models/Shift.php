<?php

namespace App\Models;

use App\Models\MudCharacteristic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shift extends Model
{
    protected $fillable = ['report_id', 'post', 'start_time', 'end_time'];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'report_id');
    }

    public function getRigAttribute()
    {
        return $this->report?->rig;
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_shifts')
            ->withPivot(['function', 'status']);
    }

    public function mudCharacteristic(): HasOne
    {
        return $this->hasOne(MudCharacteristic::class);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereHas('report', fn($q) => $q->whereDate('report_date', $date));
    }

    public function scopeForRig($query, int $rigId)
    {
        return $query->whereHas('report', fn($q) => $q->where('rig_id', $rigId));
    }
}
