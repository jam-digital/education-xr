<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasLabel
{
    case CardOnline = 'card_online';
    case BankInvoice = 'bank_invoice_school';
    case ManualAdmin = 'manual_admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::CardOnline => 'Картка онлайн',
            self::BankInvoice => 'Рахунок школи (казначейство)',
            self::ManualAdmin => 'Вручну адміном',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CardOnline => 'info',
            self::BankInvoice => 'primary',
            self::ManualAdmin => 'gray',
        };
    }
}
