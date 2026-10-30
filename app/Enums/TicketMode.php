<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketMode: string implements HasColor, HasLabel
{
    case Online = 'online_realtime';
    case Offline = 'offline_cached_sync';
    case GraceReentry = 'grace_period_reentry';
    case AdminAdjustment = 'admin_adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Online => 'Онлайн',
            self::Offline => 'Офлайн (синхр.)',
            self::GraceReentry => 'Перезапуск (grace)',
            self::AdminAdjustment => 'Коригування адміном',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Online => 'info',
            self::Offline => 'warning',
            self::GraceReentry => 'gray',
            self::AdminAdjustment => 'primary',
        };
    }
}
