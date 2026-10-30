<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasColor, HasLabel
{
    case SuperAdmin = 'super_admin';
    case Manager = 'methodist_manager';
    case FinanceViewer = 'finance_viewer';
    case Client = 'school_client';

    public function isStaff(): bool
    {
        return $this !== self::Client;
    }

    /** @return array<string, string> лише ролі персоналу — для форми співробітника */
    public static function staffOptions(): array
    {
        return collect(self::cases())->filter->isStaff()->mapWithKeys(fn (self $r) => [$r->value => $r->getLabel()])->all();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Суперадмін',
            self::Manager => 'Методист-менеджер',
            self::FinanceViewer => 'Фінанси',
            self::Client => 'Заклад освіти',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Manager => 'info',
            self::FinanceViewer => 'warning',
            self::Client => 'gray',
        };
    }
}
