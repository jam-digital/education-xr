<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AuditAction: string implements HasColor, HasLabel
{
    case GrantAccess = 'grant_access';
    case RevokeAccess = 'revoke_access';
    case ManualTicketEdit = 'manual_ticket_edit';
    case DeviceSwap = 'device_swap';
    case ManualPayment = 'manual_payment';
    case DeviceRegister = 'device_register';
    case DeviceBlock = 'device_block';
    case BuildPublish = 'build_publish';
    case BulkOperation = 'bulk_operation';
    case AccountStatus = 'account_status';

    public function getLabel(): string
    {
        return match ($this) {
            self::GrantAccess => 'Надано доступ',
            self::RevokeAccess => 'Відкликано доступ',
            self::ManualTicketEdit => 'Коригування запусків',
            self::DeviceSwap => 'Заміна шолома',
            self::ManualPayment => 'Ручна оплата',
            self::DeviceRegister => 'Реєстрація окулярів',
            self::DeviceBlock => 'Блокування окулярів',
            self::BuildPublish => 'Публікація білду',
            self::BulkOperation => 'Масова операція',
            self::AccountStatus => 'Статус акаунта',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::GrantAccess => 'success',
            self::RevokeAccess => 'danger',
            self::ManualTicketEdit => 'warning',
            self::DeviceSwap => 'info',
            self::ManualPayment => 'primary',
            self::DeviceRegister => 'info',
            self::DeviceBlock => 'danger',
            self::BuildPublish => 'primary',
            self::BulkOperation => 'warning',
            self::AccountStatus => 'gray',
        };
    }
}
