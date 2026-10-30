<?php

namespace App\Filament\Resources\TicketLogs\Tables;

use App\Enums\SessionStatus;
use App\Enums\TicketMode;
use App\Filament\Resources\Accounts\AccountResource;
use App\Models\TicketLog;
use App\Models\VrLesson;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketLogsTable
{
    public static function configure(Table $table, bool $inAccount = false): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user', 'lesson', 'device'))
            ->defaultSort('started_at', 'desc')
            ->columns(array_values(array_filter([
                TextColumn::make('started_at')->label('Старт')->dateTime('d.m.Y H:i:s')->sortable(),
                $inAccount ? null : TextColumn::make('user.school_or_org_name')->label('Заклад')
                    ->formatStateUsing(fn ($state, TicketLog $r) => $r->user->display_name)
                    ->url(fn (TicketLog $r) => AccountResource::getUrl('view', ['record' => $r->user]))
                    ->searchable(['school_or_org_name', 'contact_person_name']),
                TextColumn::make('lesson.title')->label('Урок'),
                TextColumn::make('device.device_name')->label('Окуляри')->placeholder('—')
                    ->description(fn (TicketLog $r) => $r->device?->hardware_serial),
                TextColumn::make('mode')->label('Режим')->badge(),
                TextColumn::make('tickets_delta')->label('Квитки')->alignEnd()
                    ->formatStateUsing(fn (int $state) => $state > 0 ? "+{$state}" : (string) $state)
                    ->color(fn (int $state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),
                TextColumn::make('session_duration_sec')->label('Тривалість')->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state ? gmdate($state >= 3600 ? 'H:i:s' : 'i:s', $state) : null),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('synced_at')->label('Синхр.')->since()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('session_uuid')->label('session_uuid')->fontFamily('mono')->size('xs')->copyable()->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])))
            ->filters(array_values(array_filter([
                Filter::make('period')->label('Період')
                    ->schema([DatePicker::make('from')->label('З'), DatePicker::make('to')->label('По')])->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($query, $d) => $query->whereDate('started_at', '>=', $d))
                        ->when($data['to'] ?? null, fn ($query, $d) => $query->whereDate('started_at', '<=', $d))),
                $inAccount ? null : SelectFilter::make('user_id')->label('Заклад')
                    ->relationship('user', 'school_or_org_name', fn ($query) => $query->clients())->searchable()->preload(),
                SelectFilter::make('lesson_id')->label('Урок')->options(fn () => VrLesson::orderBy('sort')->pluck('title', 'id')),
                SelectFilter::make('mode')->label('Режим')->options(TicketMode::class),
                SelectFilter::make('status')->label('Статус')->options(SessionStatus::class),
            ])))
            ->filtersFormColumns(2);
    }
}
