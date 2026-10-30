<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Filament\Support\RoleAccess;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PaymentResource extends Resource
{
    use RoleAccess;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static array $viewRoles = [Role::FinanceViewer];

    protected static array $manageRoles = [Role::FinanceViewer];

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'Платіж';

    protected static ?string $pluralModelLabel = 'Платежі та рахунки';

    protected static string|UnitEnum|null $navigationGroup = 'Фінанси';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getNavigationBadge(): ?string
    {
        $n = Payment::where('status', PaymentStatus::Pending->value)->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Рахунки, що очікують оплати';
    }

    public static function canCreate(): bool
    {
        return false; // створення лише через дії «Провести оплату» / «Виставити рахунок»
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
