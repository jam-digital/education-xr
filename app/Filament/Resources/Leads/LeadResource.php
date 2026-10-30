<?php

namespace App\Filament\Resources\Leads;

use App\Enums\Role;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Filament\Support\RoleAccess;
use App\Models\Lead;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Звернення з форми зворотного зв'язку на лендінгу */
class LeadResource extends Resource
{
    use RoleAccess;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static array $viewRoles = [Role::Manager];

    protected static array $manageRoles = [Role::Manager];

    protected static ?string $model = Lead::class;

    protected static ?string $slug = 'leads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $modelLabel = 'Звернення';

    protected static ?string $pluralModelLabel = 'Звернення з сайту';

    protected static ?string $navigationLabel = 'Звернення';

    protected static string|UnitEnum|null $navigationGroup = 'Заклади освіти';

    protected static ?int $navigationSort = 30;

    public static function getNavigationBadge(): ?string
    {
        $n = Lead::where('is_processed', false)->count();

        return $n ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListLeads::route('/')];
    }
}
