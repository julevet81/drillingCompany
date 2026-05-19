<?php

namespace App\Models;

use App\Models\DailyReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialLog extends Model
{
    protected $fillable = [
        'report_id',
        'rig_material_id',
        'log_date',
        'consumed',
        'added',
        'remaining',
    ];

    protected $casts = [
        'log_date'  => 'date',
        'consumed'  => 'decimal:2',
        'added'     => 'decimal:2',
        'remaining' => 'decimal:2',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'report_id');
    }

    public function rigMaterial(): BelongsTo
    {
        return $this->belongsTo(RigMaterial::class);
    }
}
