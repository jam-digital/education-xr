<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\SessionStatus;
use App\Enums\TicketMode;
use App\Models\LessonLicense;
use App\Models\TicketLog;
use App\Models\User;
use App\Models\VrLesson;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LicenseService
{
    /**
     * Надати доступ до одного, кількох або всіх уроків.
     * Квитковий пул: квитки додаються до наявного активного пулу (або створюється новий).
     * Підписка / тріал / безліміт: термін продовжується від поточного valid_until, якщо доступ ще діє.
     *
     * @param  array<string>|string  $lessonIds  id уроків або 'ALL'
     * @param  array<string>  $deviceIds  id окулярів або ['ALL']
     * @return LessonLicense[]
     */
    public function grant(
        User $client,
        array|string $lessonIds,
        LicenseType $type,
        ?int $tickets = null,
        ?int $days = null,
        ?CarbonInterface $until = null,
        array $deviceIds = ['ALL'],
        ?string $reason = null,
        bool $audit = true,
    ): array {
        $lessons = $lessonIds === 'ALL' || $lessonIds === ['ALL']
            ? VrLesson::where('is_active', true)->get()
            : VrLesson::whereIn('id', (array) $lessonIds)->get();

        if ($lessons->isEmpty()) {
            throw new InvalidArgumentException('Не обрано жодного уроку.');
        }
        if ($type === LicenseType::TicketPool && ($tickets ?? 0) < 1) {
            throw new InvalidArgumentException('Вкажіть кількість запусків.');
        }
        if ($type->isTimeBased() && ! $days && ! $until) {
            throw new InvalidArgumentException('Вкажіть кількість днів або дату завершення.');
        }

        $result = DB::transaction(function () use ($client, $lessons, $type, $tickets, $days, $until, $deviceIds) {
            $out = [];
            foreach ($lessons as $lesson) {
                $out[] = $type === LicenseType::TicketPool
                    ? $this->addTickets($client, $lesson, $tickets, $deviceIds)
                    : $this->extendTime($client, $lesson, $type, $days, $until, $deviceIds);
            }

            return $out;
        });

        if ($audit) {
            Audit::log(AuditAction::GrantAccess, $client, new: [
                'apps' => $lessons->pluck('title')->all(),
                'type' => $type->value,
                'tickets' => $tickets,
                'days' => $days,
                'until' => $until?->toDateTimeString(),
                'devices' => $deviceIds,
            ], reason: $reason);
        }

        return $result;
    }

    private function addTickets(User $client, VrLesson $lesson, int $tickets, array $deviceIds): LessonLicense
    {
        $license = LessonLicense::where('user_id', $client->id)->where('lesson_id', $lesson->id)
            ->where('license_type', LicenseType::TicketPool->value)->active()
            ->lockForUpdate()->first();

        if ($license) {
            $license->increment('tickets_total_purchased', $tickets);
            $license->increment('tickets_remaining', $tickets);
            $license->update(['updated_by_admin_id' => auth()->id()]);
        } else {
            $license = LessonLicense::create([
                'user_id' => $client->id,
                'lesson_id' => $lesson->id,
                'license_type' => LicenseType::TicketPool,
                'status' => LicenseStatus::Active,
                'tickets_total_purchased' => $tickets,
                'tickets_remaining' => $tickets,
                'tickets_consumed' => 0,
                'valid_from' => now(),
                'allowed_device_ids' => $deviceIds,
                'updated_by_admin_id' => auth()->id(),
            ]);
        }

        $this->ledger($license, $tickets);

        return $license->refresh();
    }

    private function extendTime(User $client, VrLesson $lesson, LicenseType $type, ?int $days, ?CarbonInterface $until, array $deviceIds): LessonLicense
    {
        $license = LessonLicense::where('user_id', $client->id)->where('lesson_id', $lesson->id)
            ->where('license_type', '!=', LicenseType::TicketPool->value)->active()
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->lockForUpdate()->first();

        $from = $license?->valid_until?->isFuture() ? $license->valid_until : now();
        $newUntil = $until ? $until->copy() : $from->copy()->addDays($days);

        if ($license) {
            // безстрокову ліцензію не скорочуємо
            if ($license->valid_until !== null) {
                $license->valid_until = $newUntil;
            }
            // тріал, продовжений платною підпискою, стає підпискою
            if ($license->license_type === LicenseType::Trial && $type !== LicenseType::Trial) {
                $license->license_type = $type;
            }
            $license->updated_by_admin_id = auth()->id();
            $license->save();

            return $license;
        }

        return LessonLicense::create([
            'user_id' => $client->id,
            'lesson_id' => $lesson->id,
            'license_type' => $type,
            'status' => LicenseStatus::Active,
            'valid_from' => now(),
            'valid_until' => $newUntil,
            'allowed_device_ids' => $deviceIds,
            'updated_by_admin_id' => auth()->id(),
        ]);
    }

    /** Миттєве відкликання. $licenses — одна ліцензія або всі ліцензії акаунта */
    public function revoke(iterable $licenses, string $reason): int
    {
        $n = 0;
        foreach ($licenses as $license) {
            if ($license->status === LicenseStatus::Revoked) {
                continue;
            }
            $old = ['status' => $license->status->value, 'balance' => $license->balanceLabel()];
            $license->update(['status' => LicenseStatus::Revoked, 'updated_by_admin_id' => auth()->id()]);
            Audit::log(AuditAction::RevokeAccess, $license->user, old: $old + ['lesson' => $license->lesson->title],
                new: ['status' => 'revoked'], reason: $reason);
            $n++;
        }

        return $n;
    }

    public function restore(LessonLicense $license, string $reason): void
    {
        $license->update(['status' => LicenseStatus::Active, 'updated_by_admin_id' => auth()->id()]);
        Audit::log(AuditAction::GrantAccess, $license->user, old: ['status' => 'revoked'],
            new: ['status' => 'active', 'lesson' => $license->lesson->title], reason: $reason);
    }

    /** Ручне додавання (+) чи списання (−) квитків з обов’язковою причиною */
    public function adjustBalance(LessonLicense $license, int $delta, string $reason): LessonLicense
    {
        if ($delta === 0) {
            throw new InvalidArgumentException('Зміна має бути ненульовою.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Вкажіть причину коригування.');
        }

        return DB::transaction(function () use ($license, $delta, $reason) {
            $license = LessonLicense::whereKey($license->id)->lockForUpdate()->firstOrFail();
            if ($license->license_type !== LicenseType::TicketPool) {
                throw new InvalidArgumentException('Коригувати запуски можна лише для квиткового пулу.');
            }
            if ($license->tickets_remaining + $delta < 0) {
                throw new InvalidArgumentException("Не можна списати більше, ніж залишилось ({$license->tickets_remaining}).");
            }
            $old = $license->only(['tickets_remaining', 'tickets_total_purchased']);
            $license->tickets_remaining += $delta;
            if ($delta > 0) {
                $license->tickets_total_purchased += $delta;
            }
            $license->updated_by_admin_id = auth()->id();
            $license->save();
            $this->ledger($license, $delta);

            Audit::log(AuditAction::ManualTicketEdit, $license->user,
                old: $old + ['lesson' => $license->lesson->title],
                new: $license->only(['tickets_remaining', 'tickets_total_purchased']) + ['delta' => $delta],
                reason: $reason);

            return $license;
        });
    }

    /** Рядок у журналі квитків для кожної зміни балансу адміном/оплатою */
    private function ledger(LessonLicense $license, int $delta): void
    {
        TicketLog::create([
            'license_id' => $license->id,
            'user_id' => $license->user_id,
            'lesson_id' => $license->lesson_id,
            'session_uuid' => (string) Str::uuid(),
            'mode' => TicketMode::AdminAdjustment,
            'tickets_delta' => $delta,
            'started_at' => now(),
            'synced_at' => now(),
            'status' => SessionStatus::Completed,
        ]);
    }
}
