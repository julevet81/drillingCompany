<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportTool extends Model
{
    protected $fillable = ['report_id', 'drilling_tool_id', 'quantity_used', 'total_length'];

    protected $casts = [
        'quantity_used' => 'integer',
        'total_length'  => 'decimal:2',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'report_id');
    }

    public function drillingTool(): BelongsTo
    {
        return $this->belongsTo(DrillingTool::class);
    }
}
