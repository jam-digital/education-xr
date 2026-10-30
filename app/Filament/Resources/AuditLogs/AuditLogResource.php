<?php

namespace App\Filament\Resources\AuditLogs;

use App\Enums\AuditAction;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Support\RoleAccess;
use App\Models\AdminAuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** admin_audit_logs — бачить лише SuperAdmin, змінити записи не можна */
class AuditLogResource extends Resource
{
    use RoleAccess;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static array $viewRoles = [];

    protected static array $manageRoles = [];

    protected static ?string $model = AdminAuditLog::class;

    protected static ?string $slug = 'audit-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'Подія';

    protected static ?string $pluralModelLabel = 'Журнал безпеки (аудит)';

    protected static ?string $navigationLabel = 'Журнал безпеки';

    protected static string|UnitEnum|null $navigationGroup = 'Журнали';

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        $kv = fn ($state) => collect($state ?? [])->map(fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v)->all();

        return $schema->columns(2)->components([
            TextEntry::make('created_at')->label('Час')->dateTime('d.m.Y H:i:s'),
            TextEntry::make('action_type')->label('Дія')->badge(),
            TextEntry::make('admin.contact_person_name')->label('Адміністратор')->placeholder('система'),
            TextEntry::make('ip_address')->label('IP'),
            TextEntry::make('targetUser.display_name')->label('Заклад')->placeholder('—'),
            TextEntry::make('targetDevice.hardware_serial')->label('Окуляри')->placeholder('—'),
            TextEntry::make('reason_comment')->label('Причина')->placeholder('—')->columnSpanFull(),
            KeyValueEntry::make('old_value')->label('Було')->state(fn ($record) => $kv($record->old_value)),
            KeyValueEntry::make('new_value')->label('Стало')->state(fn ($record) => $kv($record->new_value)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('admin', 'targetUser', 'targetDevice'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Час')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('admin.contact_person_name')->label('Адмін')->placeholder('система')->searchable(),
                TextColumn::make('action_type')->label('Дія')->badge(),
                TextColumn::make('targetUser.school_or_org_name')->label('Заклад')->placeholder('—')
                    ->formatStateUsing(fn ($state, $record) => $record->targetUser?->display_name),
                TextColumn::make('targetDevice.hardware_serial')->label('Окуляри')->fontFamily('mono')->placeholder('—'),
                TextColumn::make('reason_comment')->label('Причина')->limit(50)->placeholder('—')->searchable(),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action_type')->label('Дія')->options(AuditAction::class),
                SelectFilter::make('admin_user_id')->label('Адміністратор')->relationship('admin', 'contact_person_name', fn ($query) => $query->staff()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
