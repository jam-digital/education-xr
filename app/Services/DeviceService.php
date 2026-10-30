<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\LessonLicense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeviceService
{
    public const PAIRING_TTL_HOURS = 24;

    /** Реєстрація окулярів за серійним номером, введеним адміном */
    public function register(User $client, string $serial, ?string $name = null, ?string $model = null): Device
    {
        $serial = trim($serial);
        $existing = Device::where('hardware_serial', $serial)->first();
        if ($existing && $existing->user_id && $existing->user_id !== $client->id) {
            throw new InvalidArgumentException("Окуляри {$serial} вже прив’язані до іншого закладу ({$existing->user->display_name}).");
        }
        if ($existing && $existing->status === DeviceStatus::Replaced) {
            throw new InvalidArgumentException("Окуляри {$serial} списані після заміни і не можуть бути зареєстровані знову.");
        }

        $device = $existing ?? new Device(['hardware_serial' => $serial]);
        $device->fill([
            'user_id' => $client->id,
            'device_name' => $name ?: $device->device_name ?: 'Шолом №'.($client->devices()->count() + 1),
            'model' => $model ?: $device->model,
            'status' => DeviceStatus::Active,
            'pairing_code' => null,
            'pairing_expires_at' => null,
        ])->save();

        Audit::log(AuditAction::DeviceRegister, $client, $device, new: $device->only(['hardware_serial', 'device_name', 'model']));

        return $device;
    }

    /**
     * 6-значний код прив’язки: заклад вводить його в окулярах (або окуляри показують свій код),
     * і шолом закріплюється за акаунтом під час першої авторизації в /api/v1/vr/device/auth.
     */
    public function createPairingCode(User $client, ?string $name = null): Device
    {
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Device::where('pairing_code', $code)->exists());

        $device = Device::create([
            'user_id' => $client->id,
            'device_name' => $name ?: 'Шолом №'.($client->devices()->count() + 1),
            'status' => DeviceStatus::PendingPairing,
            'pairing_code' => $code,
            'pairing_expires_at' => now()->addHours(self::PAIRING_TTL_HOURS),
        ]);

        Audit::log(AuditAction::DeviceRegister, $client, $device, new: ['pairing_code' => $code]);

        return $device;
    }

    /**
     * Сервісна заміна шолома: старий серійник списується, новий отримує назву
     * і всі прив’язки ліцензій старого (квитки й підписки живуть на акаунті, тому переносяться без втрат).
     */
    public function swap(Device $old, string $newSerial, ?string $model, string $reason): Device
    {
        $newSerial = trim($newSerial);
        if ($old->status === DeviceStatus::Replaced) {
            throw new InvalidArgumentException('Ці окуляри вже замінені.');
        }
        if ($newSerial === $old->hardware_serial) {
            throw new InvalidArgumentException('Новий серійний номер збігається зі старим.');
        }
        $taken = Device::where('hardware_serial', $newSerial)->first();
        if ($taken && $taken->user_id && $taken->user_id !== $old->user_id) {
            throw new InvalidArgumentException("Окуляри {$newSerial} належать іншому закладу.");
        }

        return DB::transaction(function () use ($old, $newSerial, $model, $reason, $taken) {
            $new = $taken ?? new Device(['hardware_serial' => $newSerial]);
            $new->fill([
                'user_id' => $old->user_id,
                'device_name' => $old->device_name,
                'model' => $model ?: $old->model,
                'status' => DeviceStatus::Active,
                'pairing_code' => null,
                'pairing_expires_at' => null,
            ])->save();

            $old->update(['status' => DeviceStatus::Replaced, 'replaced_by_device_id' => $new->id]);

            $moved = 0;
            LessonLicense::where('user_id', $old->user_id)->get()->each(function (LessonLicense $l) use ($old, $new, &$moved) {
                $ids = $l->allowed_device_ids ?? ['ALL'];
                if (in_array($old->id, $ids, true)) {
                    $l->allowed_device_ids = array_values(array_unique(array_map(fn ($id) => $id === $old->id ? $new->id : $id, $ids)));
                    $l->save();
                    $moved++;
                }
            });

            Audit::log(AuditAction::DeviceSwap, $old->user, $old,
                old: ['hardware_serial' => $old->hardware_serial, 'device_id' => $old->id],
                new: ['hardware_serial' => $new->hardware_serial, 'device_id' => $new->id, 'licenses_rebound' => $moved],
                reason: $reason);

            return $new;
        });
    }

    public function setBlocked(Device $device, bool $blocked, string $reason): void
    {
        $old = $device->status->value;
        $device->update(['status' => $blocked ? DeviceStatus::Blocked : DeviceStatus::Active]);
        Audit::log(AuditAction::DeviceBlock, $device->user, $device, old: ['status' => $old],
            new: ['status' => $device->status->value], reason: $reason);
    }
}
