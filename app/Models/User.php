<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'status' => UserStatus::class,
            'low_balance_threshold' => 'integer',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role->isStaff() && $this->status === UserStatus::Active;
    }

    public function getFilamentName(): string
    {
        return $this->contact_person_name;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    /** @param  Role[]  $roles */
    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function scopeClients(Builder $q): Builder
    {
        return $q->where('role', Role::Client->value);
    }

    public function scopeStaff(Builder $q): Builder
    {
        return $q->where('role', '!=', Role::Client->value);
    }

    /** Назва для списків: організація, а якщо її нема — ПІБ */
    public function getDisplayNameAttribute(): string
    {
        return $this->school_or_org_name ?: $this->contact_person_name;
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(LessonLicense::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function ticketLogs(): HasMany
    {
        return $this->hasMany(TicketLog::class);
    }
}
