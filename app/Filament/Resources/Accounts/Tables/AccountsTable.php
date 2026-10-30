<?php

namespace App\Filament\Resources\Accounts\Tables;

use App\Enums\AuditAction;
use App\Enums\LicenseType;
use App\Enums\UserStatus;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Support\AccessForm;
use App\Filament\Support\Fmt;
use App\Models\User;
use App\Models\VrLesson;
use App\Services\Audit;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Заклад, місто, email, ЄДРПОУ або серійний номер окулярів')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['devices as devices_count' => fn ($d) => $d->where('status', 'active')])
                ->withCount(['licenses as active_licenses_count' => fn ($l) => $l->where('status', 'active')
                    ->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>', now()))])
                ->withSum(['licenses as tickets_left' => fn ($l) => $l->where('status', 'active')->where('license_type', 'ticket_pool')], 'tickets_remaining'))
            ->columns([
                TextColumn::make('school_or_org_name')->label('Заклад освіти')->weight('bold')->wrap()
                    ->formatStateUsing(fn ($state, User $record) => $record->display_name)
                    ->description(fn (User $record) => $record->school_or_org_name ? $record->contact_person_name : null)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $w) => $w
                        ->where('school_or_org_name', Fmt::like(), "%{$search}%")
                        ->orWhere('contact_person_name', Fmt::like(), "%{$search}%")
                        ->orWhere('email', Fmt::like(), "%{$search}%")
                        ->orWhere('city', Fmt::like(), "%{$search}%")
                        ->orWhere('edrpou_tax_id', Fmt::like(), "%{$search}%")
                        ->orWhereHas('devices', fn ($d) => $d->where('hardware_serial', Fmt::like(), "%{$search}%"))))
                    ->sortable(),
                TextColumn::make('city')->label('Місто')->placeholder('—')->sortable()->toggleable(),
                TextColumn::make('email')->label('Email')->copyable()->toggleable(),
                TextColumn::make('edrpou_tax_id')->label('ЄДРПОУ')->placeholder('—')->toggleable(),
                TextColumn::make('devices_count')->label('Окуляри')->badge()->color('gray')->sortable(),
                TextColumn::make('active_licenses_count')->label('Активні доступи')->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')->sortable(),
                TextColumn::make('tickets_left')->label('Запусків')->numeric()->placeholder('0')->sortable()
                    ->color(fn ($state, User $record) => $state !== null && $state < $record->low_balance_threshold ? 'danger' : null),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('created_at')->label('Створено')->date('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(UserStatus::class),
                SelectFilter::make('city')->label('Місто / громада')->multiple()
                    ->options(fn () => User::clients()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city', 'city')->all()),
                SelectFilter::make('lesson')->label('Має доступ до уроку')
                    ->options(fn () => VrLesson::orderBy('sort')->pluck('title', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn ($query, $lesson) => $query->whereHas('licenses', fn ($l) => $l->where('lesson_id', $lesson)->where('status', 'active')))),
                Filter::make('low')->label('Мало запусків')
                    ->query(fn (Builder $query) => $query->whereHas('licenses', fn ($l) => $l->where('status', 'active')->where('license_type', 'ticket_pool')
                        ->whereColumn('tickets_remaining', '<', 'users.low_balance_threshold'))),
                Filter::make('no_access')->label('Без жодного доступу')
                    ->query(fn (Builder $query) => $query->whereDoesntHave('licenses', fn ($l) => $l->where('status', 'active'))),
            ])
            ->recordUrl(fn (User $record) => AccountResource::getUrl('view', ['record' => $record]))
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkGrant')->label('Відкрити доступ до VR-уроку / предмета')->icon('heroicon-o-key')
                        ->modalHeading('Видати доступ обраним закладам')
                        ->schema(AccessForm::fields(withDevices: false))
                        ->action(fn (Collection $records, array $data) => self::bulk($records, $data, 'Доступ видано')),
                    BulkAction::make('bulkTickets')->label('Нарахувати запуски')->icon('heroicon-o-ticket')
                        ->modalHeading('Нарахувати запуски обраним закладам')
                        ->schema([
                            Select::make('lesson_ids')->label('VR-уроки')->multiple()->required()->preload()
                                ->options(fn () => VrLesson::where('is_active', true)->orderBy('sort')->pluck('title', 'id')),
                            TextInput::make('tickets')->label('Запусків кожному')->numeric()->minValue(1)->default(100)->required(),
                            Textarea::make('reason')->label('Причина')->required()->rows(2)->placeholder('Грант відділу освіти на II семестр'),
                        ])
                        ->action(fn (Collection $records, array $data) => self::bulk($records, $data + ['license_type' => LicenseType::TicketPool->value], 'Запуски нараховано')),
                    BulkAction::make('bulkTrial')->label('Активувати пробний доступ на 7 днів')->icon('heroicon-o-gift')
                        ->modalHeading('Пробний доступ обраним закладам')
                        ->schema([
                            Select::make('lesson_ids')->label('VR-уроки')->multiple()->required()->preload()
                                ->options(fn () => VrLesson::where('is_active', true)->orderBy('sort')->pluck('title', 'id')),
                            TextInput::make('days')->label('Днів')->numeric()->minValue(1)->default(7)->required(),
                            Textarea::make('reason')->label('Коментар')->rows(2),
                        ])
                        ->action(fn (Collection $records, array $data) => self::bulk($records, $data + ['license_type' => LicenseType::Trial->value, 'term_mode' => 'days'], 'Тріал відкрито')),
                ])->label('Масові операції')->visible(fn () => AccountResource::canManage()),
            ]);
    }

    private static function bulk(Collection $clients, array $data, string $done): void
    {
        $ok = 0;
        $errors = [];
        foreach ($clients as $client) {
            try {
                AccessForm::apply($client, $data);
                $ok++;
            } catch (Throwable $e) {
                $errors[] = $client->display_name.': '.$e->getMessage();
            }
        }
        Audit::log(AuditAction::BulkOperation, new: [
            'operation' => $done,
            'clients' => $clients->pluck('id')->all(),
            'data' => collect($data)->except('reason')->all(),
        ], reason: $data['reason'] ?? null);

        Notification::make()->title("{$done}: {$ok} з {$clients->count()} закладів")
            ->body($errors ? implode("\n", $errors) : null)
            ->{$errors ? 'warning' : 'success'}()->send();
    }
}
