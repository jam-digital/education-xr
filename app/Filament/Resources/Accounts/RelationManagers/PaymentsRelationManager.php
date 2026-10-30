<?php

namespace App\Filament\Resources\Accounts\RelationManagers;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'payments';

    protected static ?string $title = 'Платежі та документи';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedBanknotes;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return PaymentResource::canViewAny();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return PaymentsTable::configure($table, inAccount: true);
    }
}
