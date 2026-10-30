<?php

namespace App\Filament\Resources\VrLessons;

use App\Enums\Role;
use App\Filament\Resources\VrLessons\Pages\CreateVrLesson;
use App\Filament\Resources\VrLessons\Pages\EditVrLesson;
use App\Filament\Resources\VrLessons\Pages\ListVrLessons;
use App\Filament\Resources\VrLessons\RelationManagers\BuildsRelationManager;
use App\Filament\Resources\VrLessons\Schemas\VrLessonForm;
use App\Filament\Resources\VrLessons\Tables\VrLessonsTable;
use App\Filament\Support\RoleAccess;
use App\Models\VrLesson;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VrLessonResource extends Resource
{
    use RoleAccess;

    protected static bool $hasTitleCaseModelLabel = false;

    // Методист веде каталог уроків і білди (ТЗ 3.1), фінанси лише переглядають
    protected static array $viewRoles = [Role::Manager, Role::FinanceViewer];

    protected static array $manageRoles = [Role::Manager];

    protected static ?string $model = VrLesson::class;

    protected static ?string $slug = 'lessons';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $modelLabel = 'VR-урок';

    protected static ?string $pluralModelLabel = 'VR-уроки';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'app_key', 'android_package_name'];
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny(); // сторінку відкривають усі, поля заблоковані без прав
    }

    public static function form(Schema $schema): Schema
    {
        return VrLessonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VrLessonsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [BuildsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVrLessons::route('/'),
            'create' => CreateVrLesson::route('/create'),
            'edit' => EditVrLesson::route('/{record}/edit'),
        ];
    }
}
