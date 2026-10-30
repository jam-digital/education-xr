<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReleaseChannel: string implements HasColor, HasLabel
{
    case Production = 'production';
    case Beta = 'beta';

    public function getLabel(): string
    {
        return match ($this) {
            self::Production => 'Production',
            self::Beta => 'Beta',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Production => 'success',
            self::Beta => 'warning',
        };
    }
}
