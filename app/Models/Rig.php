<?php

namespace App\Models;

use App\Models\DrillingTool;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rig extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'manager_id',
        'code',
        'location_id',
        'status',
        'current_depth',
        'target_depth',
        'drilling_phase',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'current_depth' => 'decimal:2',
        'target_depth'  => 'decimal:2',
        'start_date'    => 'date',
        'end_date'      => 'date',
    ];

    public const STATUSES = [
        'active',
        'paused',
        'completed',
        'fishing',
        'dtm',
        'casing',
        'maintenance',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class, 'current_rig_id');
    }

    public function drillingTools(): HasMany
    {
        return $this->hasMany(DrillingTool::class);
    }

    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function rigMaterials(): HasMany
    {
        return $this->hasMany(RigMaterial::class);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getProgressPercentageAttribute(): float
    {
        if (!$this->target_depth || $this->target_depth == 0) return 0;
        return min(100, round(($this->current_depth / $this->target_depth) * 100, 2));
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date) return null;
        $diff = now()->diffInDays($this->end_date, false);
        return $diff >= 0 ? (int) $diff : null;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
