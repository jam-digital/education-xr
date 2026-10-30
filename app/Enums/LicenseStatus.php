<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LicenseStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Paused = 'paused';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Активна',
            self::Paused => 'Призупинена',
            self::Revoked => 'Відкликана',
            self::Expired => 'Закінчилась',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Revoked => 'danger',
            self::Expired => 'gray',
        };
    }
}
