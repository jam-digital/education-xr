<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SessionStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Completed = 'completed';
    case CrashedGraceReused = 'crashed_grace_reused';
    case DeniedNoTickets = 'denied_no_tickets';
    case DeniedClockTamper = 'denied_clock_tamper';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Триває',
            self::Completed => 'Завершено',
            self::CrashedGraceReused => 'Збій, без списання',
            self::DeniedNoTickets => 'Відмова: немає запусків',
            self::DeniedClockTamper => 'Відмова: відкат годинника',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'info',
            self::Completed => 'success',
            self::CrashedGraceReused => 'gray',
            self::DeniedNoTickets => 'danger',
            self::DeniedClockTamper => 'danger',
        };
    }
}
