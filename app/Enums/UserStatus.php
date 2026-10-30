<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Banned = 'banned';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Активний',
            self::Suspended => 'Призупинено',
            self::Banned => 'Заблоковано',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Banned => 'danger',
        };
    }
}
