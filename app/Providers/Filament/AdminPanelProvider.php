<?php

namespace App\Providers\Filament;

use App\Filament\Support\InitialsAvatar;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('VR-освіта')
            ->brandLogo(asset('img/logo-white.svg'))
            ->brandLogoHeight('1.6rem')
            ->darkMode(isForced: true)
            ->favicon(asset('favicon.svg'))
            ->font('Inter')
            ->colors([
                'primary' => Color::Violet,
                'gray' => Color::Zinc,
            ])
            ->defaultAvatarProvider(InitialsAvatar::class)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->navigationGroups([
                NavigationGroup::make('Заклади освіти'),
                NavigationGroup::make('Каталог'),
                NavigationGroup::make('Фінанси'),
                NavigationGroup::make('Журнали')->collapsed(),
                NavigationGroup::make('Система')->collapsed(),
            ])
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn () => '<div class="xr-login-sub">Адмінпанель освітньої VR-платформи</div>')
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => '<link rel="stylesheet" href="'.asset('css/admin.css').'?v=2"><script src="'.asset('js/build-upload.js').'?v=1"></script>')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
