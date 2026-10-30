<?php

namespace App\Filament\Resources\TicketLogs;

use App\Enums\Role;
use App\Filament\Resources\TicketLogs\Pages\ListTicketLogs;
use App\Filament\Resources\TicketLogs\Tables\TicketLogsTable;
use App\Filament\Support\RoleAccess;
use App\Models\TicketLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Журнал запусків, офлайн-синхронізацій і коригувань (лише перегляд) */
class TicketLogResource extends Resource
{
    use RoleAccess;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static array $viewRoles = [Role::Manager, Role::FinanceViewer];

    protected static array $manageRoles = [];

    protected static ?string $model = TicketLog::class;

    protected static ?string $slug = 'ticket-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlay;

    protected static ?string $modelLabel = 'Запис';

    protected static ?string $pluralModelLabel = 'Журнал проведення VR-уроків';

    protected static ?string $navigationLabel = 'Проведені VR-уроки';

    protected static string|UnitEnum|null $navigationGroup = 'Журнали';

    public static function table(Table $table): Table
    {
        return TicketLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListTicketLogs::route('/')];
    }
}
