<?php

declare(strict_types=1);

use App\Enums\MenuContext;
use App\Enums\MenuLinkType;
use App\Filament\Resources\Shops\ShopResource;
use App\Filament\Resources\Sliders\SliderResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\CmsMenu;
use App\Models\CmsMenuItem;
use App\Services\Cms\AdminPanelNavigationBuilder;
use App\Services\Cms\LegacyAdminMenuSynchronizer;
use App\Services\Cms\MenuItemActiveMatcher;
use App\Services\Cms\MenuTreeBuilder;
use App\Services\Cms\MenuUrlResolver;
use App\Services\Cms\VoyagerIconMapper;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;

it('maps legacy voyager icon classes to heroicons', function (): void {
    $mapper = app(VoyagerIconMapper::class);

    expect($mapper->map('voyager-boat'))->toBe(Heroicon::OutlinedHome)
        ->and($mapper->map('voyager-person'))->toBe(Heroicon::OutlinedUser)
        ->and($mapper->map('voyager-youtube'))->toBe(Heroicon::OutlinedPlayCircle)
        ->and($mapper->map('voyager-unknown'))->toBeNull()
        ->and($mapper->map('not-an-icon'))->toBeNull();
});

it('resolves route and url links', function (): void {
    $menu = CmsMenu::query()->create([
        'name' => 'Test',
        'slug' => 'test-menu',
        'context' => MenuContext::Frontend,
        'location' => 'header',
        'is_active' => true,
    ]);

    $routeItem = CmsMenuItem::query()->create([
        'cms_menu_id' => $menu->id,
        'title' => 'Home',
        'link_type' => MenuLinkType::Route,
        'route_name' => 'home',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $urlItem = CmsMenuItem::query()->create([
        'cms_menu_id' => $menu->id,
        'title' => 'External',
        'link_type' => MenuLinkType::Url,
        'url' => 'https://example.com',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $resolver = app(MenuUrlResolver::class);

    expect($resolver->resolve($routeItem))->toBe(route('home'));
    expect($resolver->resolve($urlItem))->toBe('https://example.com');
});

it('builds nested menu tree', function (): void {
    $menu = CmsMenu::query()->create([
        'name' => 'Nested',
        'slug' => 'nested-menu',
        'context' => MenuContext::Frontend,
        'is_active' => true,
    ]);

    $parent = CmsMenuItem::query()->create([
        'cms_menu_id' => $menu->id,
        'title' => 'Parent',
        'link_type' => MenuLinkType::Url,
        'url' => '/parent',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    CmsMenuItem::query()->create([
        'cms_menu_id' => $menu->id,
        'parent_id' => $parent->id,
        'title' => 'Child',
        'link_type' => MenuLinkType::Url,
        'url' => '/child',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $tree = app(MenuTreeBuilder::class)->build($menu);

    expect($tree)->toHaveCount(1);
    expect($tree->first()['children'])->toHaveCount(1);
});

it('syncs legacy admin menu for filament sidebar', function (): void {
    $menu = CmsMenu::query()->updateOrCreate(
        ['slug' => LegacyAdminMenuSynchronizer::ADMIN_MENU_SLUG],
        [
            'name' => 'admin',
            'context' => MenuContext::Frontend,
            'is_active' => true,
            'replaces_panel_navigation' => false,
        ],
    );

    CmsMenuItem::query()->create([
        'cms_menu_id' => $menu->id,
        'title' => 'Users',
        'link_type' => MenuLinkType::Url,
        'url' => '',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    app(LegacyAdminMenuSynchronizer::class)->sync();

    $menu->refresh();
    $users = CmsMenuItem::query()->where('cms_menu_id', $menu->id)->where('title', 'Users')->first();

    expect($menu->context)->toBe(MenuContext::Admin)
        ->and($menu->replaces_panel_navigation)->toBeTrue()
        ->and($users->link_type)->toBe(MenuLinkType::Resource)
        ->and($users->resource_class)->toBe(UserResource::class);

    $navigation = app(AdminPanelNavigationBuilder::class)->resolve();

    expect($navigation)->not->toBeFalse();
});

it('syncs sliders menu item to filament sliders resource', function (): void {
    $menu = CmsMenu::query()->updateOrCreate(
        ['slug' => LegacyAdminMenuSynchronizer::ADMIN_MENU_SLUG],
        [
            'name' => 'admin',
            'context' => MenuContext::Admin,
            'is_active' => true,
            'replaces_panel_navigation' => true,
        ],
    );

    CmsMenuItem::query()->create([
        'cms_menu_id' => $menu->id,
        'title' => 'Sliders',
        'link_type' => MenuLinkType::Url,
        'url' => '/panel/shops',
        'sort_order' => 5,
        'is_active' => true,
    ]);

    app(LegacyAdminMenuSynchronizer::class)->sync();

    $sliders = CmsMenuItem::query()
        ->where('cms_menu_id', $menu->id)
        ->where('title', 'Sliders')
        ->first();

    expect($sliders->link_type)->toBe(MenuLinkType::Resource)
        ->and($sliders->resource_class)->toBe(SliderResource::class);
});

it('deactivates sign-ups and adds youtube guide icon during admin menu sync', function (): void {
    $menu = CmsMenu::query()->updateOrCreate(
        ['slug' => LegacyAdminMenuSynchronizer::ADMIN_MENU_SLUG],
        [
            'name' => 'admin',
            'context' => MenuContext::Admin,
            'is_active' => true,
            'replaces_panel_navigation' => true,
        ],
    );

    CmsMenuItem::query()->create([
        'cms_menu_id' => $menu->id,
        'title' => 'sign-ups',
        'link_type' => MenuLinkType::Url,
        'url' => '/panel/users',
        'sort_order' => 90,
        'is_active' => true,
    ]);

    CmsMenuItem::query()->create([
        'cms_menu_id' => $menu->id,
        'title' => 'Youtube guide',
        'link_type' => MenuLinkType::Url,
        'url' => 'https://www.youtube.com/playlist?list=PLcxIFCbE9hcA2n-K5wjlNj8kiOeaK2umQ',
        'sort_order' => 91,
        'is_active' => true,
    ]);

    app(LegacyAdminMenuSynchronizer::class)->sync();

    $signUps = CmsMenuItem::query()
        ->where('cms_menu_id', $menu->id)
        ->where('title', 'sign-ups')
        ->first();

    $youtubeGuide = CmsMenuItem::query()
        ->where('cms_menu_id', $menu->id)
        ->where('title', 'Youtube guide')
        ->first();

    expect($signUps->is_active)->toBeFalse()
        ->and($youtubeGuide->icon)->toBe('voyager-youtube');
});

it('syncs active shops menu item to filament shops list', function (): void {
    $menu = CmsMenu::query()->updateOrCreate(
        ['slug' => LegacyAdminMenuSynchronizer::ADMIN_MENU_SLUG],
        [
            'name' => 'admin',
            'context' => MenuContext::Admin,
            'is_active' => true,
            'replaces_panel_navigation' => true,
        ],
    );

    CmsMenuItem::query()->create([
        'cms_menu_id' => $menu->id,
        'title' => '.. ACTIVE SHOPS',
        'link_type' => MenuLinkType::Url,
        'url' => 'https://iziibuy.com/admin/shops?s=0&filter=equals&key=is_demo',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    app(LegacyAdminMenuSynchronizer::class)->sync();

    $activeShops = CmsMenuItem::query()
        ->where('cms_menu_id', $menu->id)
        ->where('title', '.. ACTIVE SHOPS')
        ->first();

    expect($activeShops->link_type)->toBe(MenuLinkType::Url)
        ->and($activeShops->url)->toBe('/panel/shops?active=1');
});

it('builds parent menu items as collapsible navigation groups', function (): void {
    $menu = CmsMenu::query()->create([
        'name' => 'Admin',
        'slug' => 'test-admin-groups',
        'context' => MenuContext::Admin,
        'is_active' => true,
        'replaces_panel_navigation' => true,
    ]);

    $parent = CmsMenuItem::query()->create([
        'cms_menu_id' => $menu->id,
        'title' => 'RETAILER',
        'link_type' => MenuLinkType::Url,
        'icon' => 'voyager-people',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    CmsMenuItem::query()->create([
        'cms_menu_id' => $menu->id,
        'parent_id' => $parent->id,
        'title' => 'Users',
        'link_type' => MenuLinkType::Resource,
        'resource_class' => UserResource::class,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $navigation = app(AdminPanelNavigationBuilder::class)->build($menu)->getNavigation();

    $retailerGroup = collect($navigation)
        ->first(fn (NavigationGroup $group): bool => $group->getLabel() === 'RETAILER');

    expect($retailerGroup)->not->toBeNull()
        ->and($retailerGroup->isCollapsible())->toBeTrue()
        ->and(collect($retailerGroup->getItems()))->toHaveCount(1)
        ->and($retailerGroup->getItems()[0]->getLabel())->toBe('Users');
});

it('marks filtered url menu items active when query parameters match', function (): void {
    $matcher = app(MenuItemActiveMatcher::class);

    $this->get('/panel/charges?demo=0');

    expect($matcher->matchesUrl(url('/panel/charges?demo=0')))->toBeTrue()
        ->and($matcher->matchesUrl(url('/panel/charges?demo=1')))->toBeFalse();
});

it('marks base resource menu items inactive when distinguishing query parameters are present', function (): void {
    $matcher = app(MenuItemActiveMatcher::class);

    $this->get('/panel/shops?active=1');

    expect($matcher->matchesUrl(url('/panel/shops?active=1')))->toBeTrue()
        ->and($matcher->matchesResource(ShopResource::class, ShopResource::getUrl()))->toBeFalse();
});

it('marks resource menu items active on nested resource pages', function (): void {
    $matcher = app(MenuItemActiveMatcher::class);

    $this->get('/panel/shops/1/edit');

    expect($matcher->matchesResource(ShopResource::class, ShopResource::getUrl()))->toBeTrue();
});

it('scopes menus by context', function (): void {
    CmsMenu::query()->create([
        'name' => 'Frontend menu',
        'slug' => 'fe',
        'context' => MenuContext::Frontend,
        'is_active' => true,
    ]);

    CmsMenu::query()->create([
        'name' => 'Admin menu',
        'slug' => 'admin-nav',
        'context' => MenuContext::Admin,
        'is_active' => true,
    ]);

    expect(CmsMenu::query()->forContext(MenuContext::Frontend)->where('slug', 'fe')->exists())->toBeTrue();
    expect(CmsMenu::query()->forContext(MenuContext::Admin)->where('slug', 'admin-nav')->exists())->toBeTrue();
});
