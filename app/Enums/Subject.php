<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/** Шкільний предмет VR-уроку (vr_lessons.subject_category) */
enum Subject: string implements HasColor, HasIcon, HasLabel
{
    case Physics = 'physics';
    case Chemistry = 'chemistry';
    case Mathematics = 'mathematics';
    case Biology = 'biology';
    case History = 'history';
    case Astronomy = 'astronomy';
    case StemGeneral = 'stem_general';

    public function getLabel(): string
    {
        return match ($this) {
            self::Physics => 'Фізика',
            self::Chemistry => 'Хімія',
            self::Mathematics => 'Математика',
            self::Biology => 'Біологія',
            self::History => 'Історія',
            self::Astronomy => 'Астрономія',
            self::StemGeneral => 'STEM (загальне)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Physics => 'info',
            self::Chemistry => 'success',
            self::Mathematics => 'warning',
            self::Biology => 'success',
            self::History => 'danger',
            self::Astronomy => 'primary',
            self::StemGeneral => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Physics => 'heroicon-o-bolt',
            self::Chemistry => 'heroicon-o-beaker',
            self::Mathematics => 'heroicon-o-calculator',
            self::Biology => 'heroicon-o-bug-ant',
            self::History => 'heroicon-o-building-library',
            self::Astronomy => 'heroicon-o-globe-europe-africa',
            self::StemGeneral => 'heroicon-o-cpu-chip',
        };
    }

    /** Колір для графіків і обкладинок */
    public function hex(): string
    {
        return match ($this) {
            self::Physics => '#38bdf8',
            self::Chemistry => '#34d399',
            self::Mathematics => '#fbbf24',
            self::Biology => '#a3e635',
            self::History => '#f87171',
            self::Astronomy => '#a78bfa',
            self::StemGeneral => '#94a3b8',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->getLabel()])->all();
    }
}
