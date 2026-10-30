<?php

namespace App\Filament\Resources\Accounts\RelationManagers;

use App\Filament\Resources\TicketLogs\Tables\TicketLogsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TicketLogsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'ticketLogs';

    protected static ?string $title = 'Журнал запусків';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedPlay;

    public function table(Table $table): Table
    {
        return TicketLogsTable::configure($table, inAccount: true);
    }
}
