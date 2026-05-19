<?php

namespace App\Models;

use App\Models\DailyReportTool;
use App\Models\ToolType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrillingTool extends Model
{
    protected $fillable = [
        'tool_type_id',
        'name',
        'external_diameter',
        'unit_length',
        'total_quantity',
        'status',
        'rig_id',
    ];

    protected $table = 'drilling_tools';

    protected $casts = [
        'unit_length'    => 'decimal:2',
        'total_quantity' => 'integer',
    ];

    public function toolType(): BelongsTo
    {
        return $this->belongsTo(ToolType::class);
    }

    public function rig(): BelongsTo
    {
        return $this->belongsTo(Rig::class);
    }

    public function reportTools(): HasMany
    {
        return $this->hasMany(DailyReportTool::class);
    }

    public function getTotalLengthAttribute(): float
    {
        return round(($this->unit_length ?? 0) * ($this->total_quantity ?? 0), 2);
    }
}
