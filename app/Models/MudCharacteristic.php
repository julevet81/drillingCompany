<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MudCharacteristic extends Model
{
    protected $fillable = [
        'shift_id',
        'mud_density',
        'mud_viscosity',
        'mud_pH',
        'mud_filtra',
    ];

    protected $casts = [
        'mud_density'   => 'decimal:2',
        'mud_viscosity' => 'decimal:2',
        'mud_pH'        => 'decimal:2',
        'mud_filtra'    => 'decimal:2',
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
