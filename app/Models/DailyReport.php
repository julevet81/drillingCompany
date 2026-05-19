<?php

namespace App\Models;

use App\Models\DailyReportTool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'rig_id',
        'report_date',
        'created_by',
        'depth_start',
        'depth_end',
        'daily_progress',
        'workers_count',
        'fuel_consumption',
        'incidents',
        'npt_hours',
        'npt_cause',
        'notes',
        'status',
    ];

    protected $casts = [
        'report_date'      => 'date',
        'depth_start'      => 'decimal:2',
        'depth_end'        => 'decimal:2',
        'daily_progress'   => 'decimal:2',
        'fuel_consumption' => 'decimal:2',
        'npt_hours'        => 'decimal:2',
        'incidents'        => 'integer',
        'workers_count'    => 'integer',
    ];

    public const STATUSES = ['draft', 'submitted', 'approved'];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function rig(): BelongsTo
    {
        return $this->belongsTo(Rig::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tools(): HasMany
    {
        return $this->hasMany(DailyReportTool::class, 'report_id');
    }

    public function reportEquipments(): HasMany
    {
        return $this->hasMany(DailyReportEquipment::class, 'report_id');
    }

    public function reportEmployees(): HasMany
    {
        return $this->hasMany(DailyReportEmployee::class, 'report_id');
    }

    public function materialLogs(): HasMany
    {
        return $this->hasMany(MaterialLog::class, 'report_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForRig($query, int $rigId)
    {
        return $query->where('rig_id', $rigId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('report_date', $date);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    public function getTotalBhaLengthAttribute(): float
    {
        return (float) $this->tools->sum('total_length');
    }
}
