<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LicenseType: string implements HasColor, HasLabel
{
    case TicketPool = 'ticket_pool';
    case TimeSubscription = 'time_subscription';
    case Trial = 'trial';
    case UnlimitedPromo = 'unlimited_school_promo';

    /** Доступ за часом (безліміт запусків до valid_until), а не за квитками */
    public function isTimeBased(): bool
    {
        return $this !== self::TicketPool;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::TicketPool => 'Квитковий пул',
            self::TimeSubscription => 'Часова підписка',
            self::Trial => 'Пробний період',
            self::UnlimitedPromo => 'Шкільний безліміт (акція)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TicketPool => 'primary',
            self::TimeSubscription => 'info',
            self::Trial => 'warning',
            self::UnlimitedPromo => 'success',
        };
    }
}
