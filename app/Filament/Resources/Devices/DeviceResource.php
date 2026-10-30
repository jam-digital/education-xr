<?php

namespace App\Filament\Resources\Devices;

use App\Enums\DeviceStatus;
use App\Enums\Role;
use App\Filament\Resources\Devices\Pages\EditDevice;
use App\Filament\Resources\Devices\Pages\ListDevices;
use App\Filament\Resources\Devices\Schemas\DeviceForm;
use App\Filament\Resources\Devices\Tables\DevicesTable;
use App\Filament\Support\RoleAccess;
use App\Models\Device;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DeviceResource extends Resource
{
    use RoleAccess;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static array $viewRoles = [Role::Manager];

    protected static array $manageRoles = [Role::Manager];

    protected static ?string $model = Device::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static ?string $modelLabel = 'Шкільні VR-окуляри';

    protected static ?string $pluralModelLabel = 'VR-окуляри';

    protected static string|UnitEnum|null $navigationGroup = 'Заклади освіти';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'hardware_serial';

    public static function getGloballySearchableAttributes(): array
    {
        return ['hardware_serial', 'device_name', 'pairing_code'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter(['Назва' => $record->device_name, 'Заклад' => $record->user?->display_name]);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Device::where('status', DeviceStatus::PendingPairing->value)->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Очікують прив’язки';
    }

    public static function form(Schema $schema): Schema
    {
        return DeviceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DevicesTable::configure($table, manage: fn () => static::canManage());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevices::route('/'),
            'edit' => EditDevice::route('/{record}/edit'),
        ];
    }
}
