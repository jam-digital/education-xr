<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonLicense extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'license_type' => LicenseType::class,
            'status' => LicenseStatus::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'auto_renew' => 'boolean',
            'allowed_device_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(VrLesson::class, 'lesson_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_admin_id');
    }

    public function ticketLogs(): HasMany
    {
        return $this->hasMany(TicketLog::class, 'license_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', LicenseStatus::Active->value);
    }

    /** Чи дає ліцензія запуск прямо зараз */
    public function isUsable(): bool
    {
        if ($this->status !== LicenseStatus::Active) {
            return false;
        }
        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        return $this->license_type->isTimeBased() || $this->tickets_remaining > 0;
    }

    /** Короткий опис залишку: «68 / 100 квитків» або «до 30.11.2026 (18 дн.)» */
    public function balanceLabel(): string
    {
        if ($this->license_type->isTimeBased()) {
            if (! $this->valid_until) {
                return 'безстроково';
            }
            $days = (int) now()->startOfDay()->diffInDays($this->valid_until->copy()->startOfDay(), false);

            return 'до '.$this->valid_until->format('d.m.Y H:i').($days >= 0 ? " ({$days} дн.)" : ' (минуло)');
        }

        return "{$this->tickets_remaining} / {$this->tickets_total_purchased} запусків";
    }
}
