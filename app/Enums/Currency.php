<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Currency: string implements HasColor, HasLabel
{
    case UAH = 'UAH';
    case USD = 'USD';
    case EUR = 'EUR';

    public function getLabel(): string
    {
        return match ($this) {
            self::UAH => '₴ UAH',
            self::USD => '$ USD',
            self::EUR => '€ EUR',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::UAH => 'gray',
            self::USD => 'gray',
            self::EUR => 'gray',
        };
    }
}
