<?php

namespace App\Filament\Resources\Devices\Tables;

use App\Enums\DeviceStatus;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Devices\DeviceResource;
use App\Filament\Resources\Devices\Schemas\DeviceForm;
use App\Filament\Support\Fmt;
use App\Models\Device;
use App\Models\User;
use App\Services\DeviceService;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/** Таблиця окулярів: окремий розділ «VR-окуляри» і вкладка в картці закладу */
class DevicesTable
{
    /**
     * @param  Closure(): ?User|null  $client  власник, якщо таблиця в картці закладу
     * @param  Closure(): bool  $manage
     */
    public static function configure(Table $table, ?Closure $client = null, ?Closure $manage = null): Table
    {
        $manage ??= fn () => false;
        $inAccount = $client !== null;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user', 'replacedBy'))
            ->defaultSort('last_sync_at', 'desc')
            ->searchPlaceholder('Серійний номер, назва або код прив’язки')
            ->columns(array_values(array_filter([
                TextColumn::make('device_name')->label('Назва')->weight('bold')->searchable()
                    ->description(fn (Device $r) => $r->model),
                TextColumn::make('hardware_serial')->label('Серійний номер')->fontFamily('mono')->copyable()->searchable()
                    ->placeholder(fn (Device $r) => $r->pairing_code ? 'PIN '.substr($r->pairing_code, 0, 3).'-'.substr($r->pairing_code, 3) : '—'),
                $inAccount ? null : TextColumn::make('user.school_or_org_name')->label('Заклад')
                    ->formatStateUsing(fn ($state, Device $r) => $r->user?->display_name)
                    ->url(fn (Device $r) => $r->user ? AccountResource::getUrl('view', ['record' => $r->user]) : null)
                    ->searchable(['school_or_org_name', 'contact_person_name']),
                TextColumn::make('battery_level_percent')->label('Заряд')->suffix('%')->placeholder('—')->sortable()
                    ->badge()->color(fn ($state) => Fmt::batteryColor($state)),
                TextColumn::make('free_storage_bytes')->label('Вільно')->formatStateUsing(fn ($state) => Fmt::bytes($state))->sortable(),
                TextColumn::make('installed')->label('Встановлені VR-уроки')->badge()->color('gray')->limitList(3)->expandableLimitedList()
                    ->state(fn (Device $r) => $r->installedLessonsLabels()),
                TextColumn::make('status')->label('Статус')->badge()
                    ->description(fn (Device $r) => $r->replacedBy ? '→ '.$r->replacedBy->hardware_serial : ($r->clock_tamper_detected_at ? '⚠ відкат годинника' : null)),
                TextColumn::make('last_sync_at')->label('Синхронізація')->since()->sortable()->placeholder('ніколи')
                    ->color(fn (Device $r) => $r->isOnline() ? 'success' : ($r->last_sync_at?->lt(now()->subDays(Device::STALE_OFFLINE_DAYS)) ? 'danger' : null))
                    ->description(fn (Device $r) => $r->isOnline() ? 'онлайн' : null),
            ])))
            ->filters(array_values(array_filter([
                SelectFilter::make('status')->label('Статус')->options(DeviceStatus::class),
                SelectFilter::make('model')->label('Модель')->options(DeviceForm::MODELS),
                $inAccount ? null : SelectFilter::make('user_id')->label('Заклад')
                    ->relationship('user', 'school_or_org_name', fn ($query) => $query->clients())->searchable()->preload(),
                Filter::make('online')->label('Онлайн зараз')->query(fn (Builder $query) => $query->where('last_sync_at', '>', now()->subMinutes(Device::ONLINE_MINUTES))),
                Filter::make('suspicious')->label('Підозріла активність')
                    ->query(fn (Builder $query) => $query->where('status', 'active')->where(fn ($w) => $w->whereNotNull('clock_tamper_detected_at')
                        ->orWhere('last_sync_at', '<', now()->subDays(Device::STALE_OFFLINE_DAYS)))),
            ])))
            ->headerActions($inAccount ? [
                Action::make('register')->label('+ Зареєструвати окуляри')->visible($manage)
                    ->modalHeading('Зареєструвати окуляри закладу')
                    ->schema([
                        Toggle::make('use_pin')->label('Згенерувати 6-значний PIN прив’язки замість серійного номера')->live()
                            ->helperText('PIN вводиться в окулярах при першому запуску VR-Osvita Client; діє 24 години.'),
                        TextInput::make('serial')->label('Серійний номер шолома')->placeholder('2G0YC5ZF8B0123')
                            ->hidden(fn (Get $get) => $get('use_pin'))->required(fn (Get $get) => ! $get('use_pin')),
                        TextInput::make('name')->label('Назва')->placeholder('Кабінет Хімії — Шолом №1'),
                        Select::make('model')->label('Модель')->options(DeviceForm::MODELS)->hidden(fn (Get $get) => $get('use_pin')),
                    ])
                    ->action(function (array $data) use ($client) {
                        try {
                            $svc = app(DeviceService::class);
                            if (! empty($data['use_pin'])) {
                                $d = $svc->createPairingCode($client(), $data['name'] ?? null);
                                Notification::make()->title('Код прив’язки: '.substr($d->pairing_code, 0, 3).'-'.substr($d->pairing_code, 3))
                                    ->body('Передайте його закладу. Код діє 24 години.')->success()->persistent()->send();
                            } else {
                                $svc->register($client(), $data['serial'], $data['name'] ?? null, $data['model'] ?? null);
                                Notification::make()->title('Окуляри зареєстровано')->success()->send();
                            }
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ] : [])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label('Перейменувати')->url(fn (Device $r) => DeviceResource::getUrl('edit', ['record' => $r])),
                    Action::make('swap')->label('Замінити пристрій')->icon(Heroicon::OutlinedArrowsRightLeft)->color('warning')
                        ->visible(fn (Device $r) => $manage() && $r->user_id && in_array($r->status, [DeviceStatus::Active, DeviceStatus::Blocked], true))
                        ->modalHeading(fn (Device $r) => "Сервісна заміна: {$r->device_name}")
                        ->modalDescription(fn (Device $r) => "Старий серійний номер {$r->hardware_serial} буде списано. Ліцензії, запуски й підписки перейдуть на нові окуляри.")
                        ->modalSubmitActionLabel('Замінити')
                        ->schema(fn (Device $r) => [
                            TextInput::make('serial')->label('Серійний номер нових окулярів')->required(),
                            Select::make('model')->label('Модель')->options(DeviceForm::MODELS)->default($r->model),
                            Textarea::make('reason')->label('Причина')->required()->placeholder('Розбита лінза, гарантійна заміна')->rows(2),
                        ])
                        ->action(function (Device $r, array $data) {
                            try {
                                $new = app(DeviceService::class)->swap($r, $data['serial'], $data['model'] ?? null, $data['reason']);
                                Notification::make()->title("Замінено на {$new->hardware_serial}")->success()->send();
                            } catch (Throwable $e) {
                                Notification::make()->title($e->getMessage())->danger()->send();
                            }
                        }),
                    Action::make('block')->label('Заблокувати')->icon(Heroicon::OutlinedLockClosed)->color('danger')
                        ->visible(fn (Device $r) => $manage() && $r->status === DeviceStatus::Active)
                        ->requiresConfirmation()->schema([Textarea::make('reason')->label('Причина')->required()])
                        ->action(fn (Device $r, array $data) => app(DeviceService::class)->setBlocked($r, true, $data['reason'])),
                    Action::make('unblock')->label('Розблокувати')->icon(Heroicon::OutlinedLockOpen)
                        ->visible(fn (Device $r) => $manage() && $r->status === DeviceStatus::Blocked)
                        ->schema([Textarea::make('reason')->label('Причина')->required()])
                        ->action(fn (Device $r, array $data) => app(DeviceService::class)->setBlocked($r, false, $data['reason'])),
                ]),
            ]);
    }
}
