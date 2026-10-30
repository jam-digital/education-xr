<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Заклад освіти')->columnSpan(2)->columns(2)->schema([
                TextInput::make('school_or_org_name')->label('Назва закладу освіти')->placeholder('Ліцей №142 м. Києва')->maxLength(255)->columnSpanFull(),
                TextInput::make('contact_person_name')->label('Контактна особа (ПІБ)')->required()->maxLength(255),
                TextInput::make('email')->label('Email (логін у кабінет)')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('phone')->label('Телефон')->tel(),
                TextInput::make('city')->label('Місто / громада')->placeholder('Київ')->maxLength(120)
                    ->helperText('Для пошуку й масових операцій по району чи області.'),
                TextInput::make('telegram_chat_id')->label('Telegram chat ID')->helperText('Сповіщення завучу/вчителю: мало запусків або підписка закінчується.'),
            ]),
            Section::make('Доступ')->columnSpan(1)->schema([
                Select::make('status')->label('Статус акаунта')->options(UserStatus::class)->default(UserStatus::Active)->required(),
                TextInput::make('password')->label('Пароль до кабінету')->password()->revealable()
                    ->dehydrated(fn ($state) => filled($state))->required(fn (string $operation) => $operation === 'create')
                    ->helperText('На редагуванні: порожньо = не змінювати.'),
                TextInput::make('kiosk_pin_code')->label('PIN вчителя для виходу з Kiosk-режиму')->length(4)->regex('/^\d{4}$/')->default('0000'),
                TextInput::make('low_balance_threshold')->label('Поріг «мало запусків»')->numeric()->minValue(0)->default(10),
            ]),
            Section::make('Реквізити для рахунків, казначейства й актів')->columnSpan(2)->columns(2)->schema([
                TextInput::make('edrpou_tax_id')->label('ЄДРПОУ / ІПН')->maxLength(12),
                TextInput::make('iban_treasury')->label('IBAN / казначейський рахунок')->maxLength(34),
                Textarea::make('legal_address')->label('Юридична адреса')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Нотатки')->columnSpan(1)->schema([
                Textarea::make('admin_notes')->hiddenLabel()->rows(5)->placeholder('Видно лише персоналу'),
            ]),
        ]);
    }
}
