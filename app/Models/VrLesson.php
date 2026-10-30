<?php

namespace App\Models;

use App\Enums\ReleaseChannel;
use App\Enums\Subject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VrLesson extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected $attributes = ['subject_category' => 'physics', 'is_active' => true];

    protected function casts(): array
    {
        return [
            'subject_category' => Subject::class,
            'release_channel' => ReleaseChannel::class,
            'released_at' => 'datetime',
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function builds(): HasMany
    {
        return $this->hasMany(LessonBuild::class, 'lesson_id')->orderByDesc('version_code');
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(LessonLicense::class, 'lesson_id');
    }

    public function ticketLogs(): HasMany
    {
        return $this->hasMany(TicketLog::class, 'lesson_id');
    }

    public function getTotalSizeBytesAttribute(): int
    {
        return (int) $this->apk_size_bytes + (int) $this->obb_size_bytes;
    }
}
