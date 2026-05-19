<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EmployeeShift extends Pivot
{
    protected $table     = 'employee_shifts';
    public $timestamps   = false;
    public $incrementing = false;

    protected $fillable = ['shift_id', 'employee_id', 'function', 'status'];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
