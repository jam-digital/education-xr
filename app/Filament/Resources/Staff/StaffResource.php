<?php

namespace App\Filament\Resources\Staff;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Filament\Support\RoleAccess;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Персонал адмінки: SuperAdmin, Manager, FinanceViewer */
class StaffResource extends Resource
{
    use RoleAccess;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static array $viewRoles = [];

    protected static array $manageRoles = [];

    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'Співробітник';

    protected static ?string $pluralModelLabel = 'Персонал і ролі';

    protected static string|UnitEnum|null $navigationGroup = 'Система';

    protected static bool $isGloballySearchable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->staff();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('contact_person_name')->label('ПІБ')->required(),
            TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')->label('Роль')->options(Role::staffOptions())->required()
                ->helperText('Методист: заклади освіти, каталог VR-уроків, окуляри, тріали й акції. Фінанси: платежі, рахунки, звіти.'),
            Select::make('status')->label('Статус')->options(UserStatus::class)->default(UserStatus::Active)->required(),
            TextInput::make('phone')->label('Телефон')->tel(),
            TextInput::make('password')->label('Пароль')->password()->revealable()->minLength(8)
                ->dehydrated(fn ($state) => filled($state))->required(fn (string $operation) => $operation === 'create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('contact_person_name')->label('ПІБ')->weight('bold')->searchable(),
            TextColumn::make('email')->label('Email')->searchable(),
            TextColumn::make('role')->label('Роль')->badge(),
            TextColumn::make('status')->label('Статус')->badge(),
            TextColumn::make('updated_at')->label('Змінено')->since(),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make()->hidden(fn (User $r) => $r->is(auth()->user())),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStaff::route('/')];
    }
}
