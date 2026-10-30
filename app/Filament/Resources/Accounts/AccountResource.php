<?php

namespace App\Filament\Resources\Accounts;

use App\Enums\Role;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Filament\Resources\Accounts\RelationManagers\DevicesRelationManager;
use App\Filament\Resources\Accounts\RelationManagers\LicensesRelationManager;
use App\Filament\Resources\Accounts\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Accounts\RelationManagers\TicketLogsRelationManager;
use App\Filament\Resources\Accounts\Schemas\AccountForm;
use App\Filament\Resources\Accounts\Schemas\AccountInfolist;
use App\Filament\Resources\Accounts\Tables\AccountsTable;
use App\Filament\Support\RoleAccess;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Заклади платформи (users.role = school_client): школи, ліцеї, університети, освітні центри */
class AccountResource extends Resource
{
    use RoleAccess;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static array $viewRoles = [Role::Manager, Role::FinanceViewer];

    protected static array $manageRoles = [Role::Manager];

    protected static ?string $model = User::class;

    protected static ?string $slug = 'accounts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'Заклад';

    protected static ?string $pluralModelLabel = 'Акаунти закладів освіти';

    protected static ?string $navigationLabel = 'Заклади освіти';

    protected static string|UnitEnum|null $navigationGroup = 'Заклади освіти';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'school_or_org_name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->clients();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['school_or_org_name', 'contact_person_name', 'email', 'city', 'edrpou_tax_id', 'devices.hardware_serial'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->display_name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter(['Email' => $record->email, 'ЄДРПОУ' => $record->edrpou_tax_id]);
    }

    public static function form(Schema $schema): Schema
    {
        return AccountForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AccountInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LicensesRelationManager::class,
            DevicesRelationManager::class,
            PaymentsRelationManager::class,
            TicketLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'view' => ViewAccount::route('/{record}'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }
}
