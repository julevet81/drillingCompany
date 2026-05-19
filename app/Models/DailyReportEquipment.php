<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportEquipment extends Model
{
    protected $fillable = ['report_id', 'equipment_id', 'status'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'report_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
