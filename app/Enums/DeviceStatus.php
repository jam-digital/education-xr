<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeviceStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Replaced = 'decommissioned_replaced';
    case PendingPairing = 'pending_pairing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Активний',
            self::Blocked => 'Заблоковано',
            self::Replaced => 'Списано (замінено)',
            self::PendingPairing => 'Очікує прив’язки',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Blocked => 'danger',
            self::Replaced => 'gray',
            self::PendingPairing => 'warning',
        };
    }
}
