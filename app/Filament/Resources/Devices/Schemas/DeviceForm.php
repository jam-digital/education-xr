<?php

namespace App\Filament\Resources\Devices\Schemas;

use App\Filament\Support\Fmt;
use App\Models\Device;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeviceForm
{
    public const MODELS = ['Meta Quest 3' => 'Meta Quest 3', 'Meta Quest 3S' => 'Meta Quest 3S', 'Meta Quest 2' => 'Meta Quest 2', 'Meta Quest Pro' => 'Meta Quest Pro', 'Pico 4' => 'Pico 4', 'Pico 4 Ultra' => 'Pico 4 Ultra'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Окуляри')->columnSpan(2)->columns(2)->schema([
                TextInput::make('device_name')->label('Назва')->placeholder('Кабінет Хімії — Шолом №1')->required(),
                Select::make('model')->label('Модель')->options(self::MODELS)->searchable(),
                TextInput::make('hardware_serial')->label('Серійний номер')->disabled()->dehydrated(false),
                Select::make('user_id')->label('Заклад')->relationship('user', 'school_or_org_name', fn ($query) => $query->clients())
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)->searchable()->preload()->disabled()->dehydrated(false),
            ]),
            Section::make('Телеметрія')->columnSpan(1)->schema([
                TextEntry::make('status')->label('Статус')->badge(),
                TextEntry::make('battery_level_percent')->label('Заряд')->suffix('%')->placeholder('—'),
                TextEntry::make('free_storage_bytes')->label('Вільне місце')->formatStateUsing(fn ($state) => Fmt::bytes($state)),
                TextEntry::make('os_version')->label('ОС')->placeholder('—'),
                TextEntry::make('client_hub_version')->label('VR-Osvita Client')->placeholder('—'),
                TextEntry::make('last_sync_at')->label('Остання синхронізація')->since()->placeholder('ніколи'),
                TextEntry::make('last_ip')->label('IP')->placeholder('—'),
                TextEntry::make('installed')->label('Встановлені VR-уроки')
                    ->state(fn (Device $record) => $record->installedLessonsLabels() ?: ['—'])->listWithLineBreaks(),
            ]),
        ]);
    }
}
