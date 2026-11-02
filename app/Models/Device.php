<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use HasFactory, HasUuids;

    /** Окуляри вважаються онлайн, якщо синхронізувались не пізніше ніж стільки хвилин тому */
    public const ONLINE_MINUTES = 10;

    /** Офлайн довше за стільки днів — підозріла активність (ТЗ 3.1) */
    public const STALE_OFFLINE_DAYS = 5;

    protected $guarded = ['id'];

    protected $hidden = ['api_token_hash'];

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'installed_lessons_state' => 'array',
            'last_sync_at' => 'datetime',
            'pairing_expires_at' => 'datetime',
            'clock_tamper_detected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_device_id');
    }

    public function isOnline(): bool
    {
        return $this->last_sync_at?->gt(now()->subMinutes(self::ONLINE_MINUTES)) ?? false;
    }

    /** @return string[] назви встановлених VR-уроків з версіями */
    public function installedLessonsLabels(): array
    {
        return collect($this->installed_lessons_state ?? [])
            ->map(fn ($v, $k) => is_array($v) ? ($v['title'] ?? $k).' '.($v['version'] ?? '') : "$k $v")
            ->values()->all();
    }
}
