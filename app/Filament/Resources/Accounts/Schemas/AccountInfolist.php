<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Models\User;
use App\Models\VrLesson;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class AccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Заклад освіти')->columnSpan(2)->columns(3)->schema([
                TextEntry::make('school_or_org_name')->label('Назва закладу')->placeholder('—')->weight('bold')->columnSpan(2),
                TextEntry::make('status')->label('Статус')->badge(),
                TextEntry::make('contact_person_name')->label('Контактна особа'),
                TextEntry::make('email')->label('Email')->copyable(),
                TextEntry::make('phone')->label('Телефон')->placeholder('—'),
                TextEntry::make('city')->label('Місто / громада')->placeholder('—'),
                TextEntry::make('edrpou_tax_id')->label('ЄДРПОУ')->placeholder('—')->copyable(),
                TextEntry::make('iban_treasury')->label('IBAN / казначейський рахунок')->placeholder('—'),
                TextEntry::make('legal_address')->label('Юр. адреса')->placeholder('—'),
            ]),
            Section::make('Налаштування')->columnSpan(1)->schema([
                TextEntry::make('kiosk_pin_code')->label('PIN вчителя (вихід з кіоску)'),
                TextEntry::make('low_balance_threshold')->label('Поріг «мало запусків»'),
                TextEntry::make('telegram_chat_id')->label('Telegram')->placeholder('не підключено'),
                TextEntry::make('created_at')->label('Заклад з')->date('d.m.Y'),
            ]),
            Section::make('Доступні VR-уроки та предмети')->description('Статус кожного уроку платформи для цього закладу, згруповано за предметами')
                ->columnSpanFull()->schema([
                    View::make('filament.accounts.lesson-matrix')
                        ->viewData(fn (User $record) => [
                            'lessons' => VrLesson::orderBy('subject_category')->orderBy('sort')->get(),
                            'licenses' => $record->licenses()->with('lesson')->get()->groupBy('lesson_id'),
                        ]),
                ]),
        ]);
    }
}
