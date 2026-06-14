<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
// use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'password',
        'photo',
        'is_active',
    ];

    protected $appends = ['photo_url'];
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function managedRigs(): HasMany
    {
        return $this->hasMany(Rig::class, 'manager_id');
    }

    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class, 'created_by');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    // public function isSuperAdmin(): bool
    // {
    //     return $this->hasRole('super_admin');
    // }

    // public function isManager(): bool
    // {
    //     return $this->hasRole('well_manager');
    // }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo
        ? asset($this->photo)
        : null;
    }
}
