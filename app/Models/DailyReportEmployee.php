<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportEmployee extends Model
{
    protected $fillable = ['report_id', 'shift_id', 'present'];

    protected $casts = ['present' => 'boolean'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'report_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
