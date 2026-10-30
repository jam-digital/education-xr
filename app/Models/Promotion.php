<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PromoType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'promo_type' => PromoType::class,
            'currency' => Currency::class,
            'price' => 'decimal:2',
            'included_lesson_ids' => 'array',
            'target_user_ids' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function coversAllLessons(): bool
    {
        return in_array('ALL', $this->included_lesson_ids ?? ['ALL'], true);
    }

    /** @return array<string> id уроків, які дає акція */
    public function lessonIds(): array
    {
        return $this->coversAllLessons()
            ? VrLesson::where('is_active', true)->pluck('id')->all()
            : array_values($this->included_lesson_ids ?? []);
    }

    public function isRunning(): bool
    {
        return $this->is_active
            && (! $this->starts_at || $this->starts_at->isPast())
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
