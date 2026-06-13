<?php

namespace App\Models;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['full_name', 'photo', 'position_id'];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo
        ? asset($this->photo)
        : null;
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'employee_shifts')
            ->withPivot(['function', 'status']);
    }

    public function todayShift()
    {
        return $this->shifts()
            ->whereDate('shifts.date', today())
            ->latest('shifts.date')
            ->first();
    }
}