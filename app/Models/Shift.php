<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Shift extends Model
{
    protected $fillable = ['report_id', 'periode'];

    protected $casts = ['date' => 'date'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'report_id');
    }

    // الـ rig نصل إليه عبر التقرير
    public function getRigAttribute()
    {
        return $this->report?->rig;
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_shifts')
            ->withPivot(['function', 'status']);
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
