<?php

namespace App\Filament\Resources\Accounts\RelationManagers;

use App\Filament\Resources\Devices\DeviceResource;
use App\Filament\Resources\Devices\Tables\DevicesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DevicesRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'devices';

    protected static ?string $title = 'VR-окуляри';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedEye;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->devices()->where('status', 'active')->count();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return DevicesTable::configure($table, client: fn () => $this->getOwnerRecord(), manage: fn () => DeviceResource::canManage());
    }
}
