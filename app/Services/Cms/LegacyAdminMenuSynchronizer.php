<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Enums\MenuContext;
use App\Enums\MenuLinkType;
use App\Filament\Resources\Changelogs\ChangelogResource;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\EnterpriseOnboardings\EnterpriseOnboardingResource;
use App\Filament\Resources\Faqs\FaqResource;
use App\Filament\Resources\Languages\LanguageResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\PaymentBadges\PaymentBadgeResource;
use App\Filament\Resources\PaymentMethodAccesses\PaymentMethodAccessResource;
use App\Filament\Resources\PostCategories\PostCategoryResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\RetailerEarnings\RetailerEarningResource;
use App\Filament\Resources\RetailerMetas\RetailerMetaResource;
use App\Filament\Resources\RetailerTypes\RetailerTypeResource;
use App\Filament\Resources\Shops\ShopResource;
use App\Filament\Resources\Sliders\SliderResource;
use App\Filament\Resources\SubscriptionCharges\SubscriptionChargeResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\CmsMenu;
use App\Models\CmsMenuItem;
use Filament\Resources\Resource;
use Illuminate\Support\Str;

final class LegacyAdminMenuSynchronizer
{
    /**
     * Voyager admin menu slug after CMS migration.
     */
    public const ADMIN_MENU_SLUG = 'admin';

    /**
     * @var array<string, array{type: MenuLinkType, route?: string, resource?: class-string<resource>, page?: class-string<Page>, url?: string, icon?: string}>
     */
    private const TITLE_LINKS = [
        'dashboard' => ['type' => MenuLinkType::Route, 'route' => 'filament.admin.pages.dashboard'],
        'users' => ['type' => MenuLinkType::Resource, 'resource' => UserResource::class],
        'shops' => ['type' => MenuLinkType::Resource, 'resource' => ShopResource::class],
        'active shops' => ['type' => MenuLinkType::Url, 'url' => '/panel/shops?active=1'],
        'orders' => ['type' => MenuLinkType::Resource, 'resource' => OrderResource::class],
        'products' => ['type' => MenuLinkType::Resource, 'resource' => ProductResource::class],
        'retailers' => ['type' => MenuLinkType::Resource, 'resource' => RetailerMetaResource::class],
        'all earnings' => ['type' => MenuLinkType::Resource, 'resource' => RetailerEarningResource::class],
        'pending withdrawal' => ['type' => MenuLinkType::Route, 'route' => 'filament.admin.pages.retailers.withdrawals'],
        'retailer types' => ['type' => MenuLinkType::Resource, 'resource' => RetailerTypeResource::class],
        'coupons' => ['type' => MenuLinkType::Resource, 'resource' => CouponResource::class],
        'charges' => ['type' => MenuLinkType::Url, 'url' => '/panel/charges?demo=0'],
        'demo charges' => ['type' => MenuLinkType::Url, 'url' => '/panel/charges?demo=1'],
        'menus' => ['type' => MenuLinkType::Route, 'route' => 'filament.admin.resources.cms-menu-items.menu-builder'],
        'pages' => ['type' => MenuLinkType::Resource, 'resource' => PageResource::class],
        'settings' => ['type' => MenuLinkType::Route, 'route' => 'filament.admin.pages.settings'],
        'tickets' => ['type' => MenuLinkType::Resource, 'resource' => TicketResource::class],
        'changelogs' => ['type' => MenuLinkType::Resource, 'resource' => ChangelogResource::class],
        'server stats' => ['type' => MenuLinkType::Url, 'url' => 'https://stats.uptimerobot.com/YBzvrCD1OL'],
        'post' => ['type' => MenuLinkType::Resource, 'resource' => PostResource::class],
        'posts' => ['type' => MenuLinkType::Resource, 'resource' => PostResource::class],
        'categories' => ['type' => MenuLinkType::Resource, 'resource' => PostCategoryResource::class],
        'languages' => ['type' => MenuLinkType::Resource, 'resource' => LanguageResource::class],
        'plugins' => ['type' => MenuLinkType::Resource, 'resource' => PaymentMethodAccessResource::class],
        'payment badges' => ['type' => MenuLinkType::Resource, 'resource' => PaymentBadgeResource::class],
        'subscription charges' => ['type' => MenuLinkType::Resource, 'resource' => SubscriptionChargeResource::class],
        'enterprise onboardings' => ['type' => MenuLinkType::Resource, 'resource' => EnterpriseOnboardingResource::class],
        'faq' => ['type' => MenuLinkType::Resource, 'resource' => FaqResource::class],
        'youtube guide' => ['type' => MenuLinkType::Url, 'url' => 'https://www.youtube.com/playlist?list=PLcxIFCbE9hcA2n-K5wjlNj8kiOeaK2umQ', 'icon' => 'voyager-youtube'],
        'contactform' => ['type' => MenuLinkType::Resource, 'resource' => TicketResource::class],
        'sliders' => ['type' => MenuLinkType::Resource, 'resource' => SliderResource::class],
    ];

    public function sync(): CmsMenu
    {
        $menu = CmsMenu::query()->firstOrCreate(
            ['slug' => self::ADMIN_MENU_SLUG],
            [
                'name' => 'Admin sidebar',
                'context' => MenuContext::Admin,
                'is_active' => true,
                'replaces_panel_navigation' => true,
            ],
        );

        $menu->update([
            'name' => 'Admin sidebar',
            'context' => MenuContext::Admin,
            'is_active' => true,
            'replaces_panel_navigation' => true,
        ]);

        $menu->allItems()->each(function (CmsMenuItem $item): void {
            if ($this->normalizeTitle($item->title) === 'sign-ups') {
                $item->update(['is_active' => false]);

                return;
            }

            $this->syncItem($item);
        });

        return $menu->fresh();
    }

    private function syncItem(CmsMenuItem $item): void
    {
        $normalizedTitle = $this->normalizeTitle($item->title);
        $mapping = self::TITLE_LINKS[$normalizedTitle] ?? null;

        if ($mapping !== null) {
            $this->applyMapping($item, $mapping);

            return;
        }

        if (filled($item->url)) {
            $item->update([
                'link_type' => MenuLinkType::Url,
                'url' => $this->normalizeLegacyUrl($item->url),
            ]);

            return;
        }

        if ($normalizedTitle === 'active shops' || str_contains($normalizedTitle, 'active shops')) {
            $item->update([
                'link_type' => MenuLinkType::Resource,
                'resource_class' => ShopResource::class,
                'route_name' => null,
                'url' => null,
            ]);
        }
    }

    /**
     * @param  array{type: MenuLinkType, route?: string, resource?: class-string<resource>, page?: class-string<Page>, url?: string, icon?: string}  $mapping
     */
    private function applyMapping(CmsMenuItem $item, array $mapping): void
    {
        $attributes = [
            'link_type' => $mapping['type'],
            'route_name' => null,
            'resource_class' => null,
            'url' => null,
        ];

        if (isset($mapping['icon'])) {
            $attributes['icon'] = $mapping['icon'];
        }

        if ($mapping['type'] === MenuLinkType::Route && isset($mapping['route'])) {
            $attributes['route_name'] = $mapping['route'];
        }

        if ($mapping['type'] === MenuLinkType::Resource && isset($mapping['resource'])) {
            $attributes['resource_class'] = $mapping['resource'];
        }

        if ($mapping['type'] === MenuLinkType::Url && isset($mapping['url'])) {
            $attributes['url'] = $this->normalizeLegacyUrl($mapping['url']);
        }

        $item->update($attributes);
    }

    private function normalizeTitle(string $title): string
    {
        return Str::of($title)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s\-]/', '')
            ->squish()
            ->toString();
    }

    private function normalizeLegacyUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '//')) {
            return $url;
        }

        $path = ltrim($url, '/');

        if (str_starts_with($path, 'admin/')) {
            $path = 'panel/'.substr($path, strlen('admin/'));
        }

        return '/'.ltrim($path, '/');
    }
}
