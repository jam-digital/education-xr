<?php

namespace App\Filament\Support;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;

/**
 * Рольова модель ТЗ 3.1 на рівні ресурсу:
 *   SuperAdmin — усе; Manager (методист) — заклади освіти, каталог VR-уроків, окуляри, тріали, акції; FinanceViewer — фінанси і перегляд закладів.
 * Ресурс задає static array $viewRoles і $manageRoles (значення Role).
 */
trait RoleAccess
{
    protected static function userHas(array $roles): bool
    {
        $user = auth()->user();

        return $user && ($user->role === Role::SuperAdmin || in_array($user->role, $roles, true));
    }

    public static function canViewAny(): bool
    {
        return static::userHas(static::$viewRoles ?? []);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canManage(): bool
    {
        return static::userHas(static::$manageRoles ?? []);
    }

    public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManage();
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->role === Role::SuperAdmin;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->role === Role::SuperAdmin;
    }
}
