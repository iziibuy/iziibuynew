<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\Localization;
use App\Services\Cms\AdminPanelNavigationBuilder;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Enums\GlobalSearchPosition;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
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
            ->path('panel')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->darkMode(false)
            ->brandName(__('Iziibuy Admin'))
            ->colors([
                'primary' => Color::hex('#1f3763'),
                'warning' => Color::hex('#be9000'),
            ])
            ->navigationGroups([
                'commerce' => NavigationGroup::make()
                    ->label(__('Commerce'))
                    ->icon(Heroicon::OutlinedShoppingBag),
                'finance' => NavigationGroup::make()
                    ->label(__('Finance'))
                    ->icon(Heroicon::OutlinedBanknotes),
                'site' => NavigationGroup::make()
                    ->label(__('Site & CMS'))
                    ->icon(Heroicon::OutlinedGlobeAlt),
                'people' => NavigationGroup::make()
                    ->label(__('People'))
                    ->icon(Heroicon::OutlinedUsers),
                'retailers' => NavigationGroup::make()
                    ->label(__('Retailers'))
                    ->icon(Heroicon::OutlinedBuildingStorefront),
                'support' => NavigationGroup::make()
                    ->label(__('Support'))
                    ->icon(Heroicon::OutlinedLifebuoy),
                'onboarding' => NavigationGroup::make()
                    ->label(__('Onboarding'))
                    ->icon(Heroicon::OutlinedBuildingOffice2),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch(position: GlobalSearchPosition::Topbar)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->navigation(fn (): NavigationBuilder|bool => app(AdminPanelNavigationBuilder::class)->resolve())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                Localization::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
