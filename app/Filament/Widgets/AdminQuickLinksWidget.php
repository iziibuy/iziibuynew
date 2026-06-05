<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\RetailerWithdrawalsPage;
use App\Filament\Pages\ServerStatusPage;
use App\Filament\Pages\SiteSettingsPage;
use App\Filament\Resources\Changelogs\ChangelogResource;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\CmsMenuItems\Pages\MenuBuilder;
use App\Filament\Resources\CmsMenus\CmsMenuResource;
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
use App\Filament\Resources\Shops\ShopResource;
use App\Filament\Resources\SubscriptionCharges\SubscriptionChargeResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Widgets\Widget;

class AdminQuickLinksWidget extends Widget
{
    protected string $view = 'filament.widgets.admin-quick-links';

    protected static ?int $sort = -18;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return list<array{label: string, url: string}>
     */
    public function getLinks(): array
    {
        return [
            ['label' => __('Orders'), 'url' => OrderResource::getUrl()],
            ['label' => __('Shops'), 'url' => ShopResource::getUrl()],
            ['label' => __('Products'), 'url' => ProductResource::getUrl()],
            ['label' => __('Charges'), 'url' => ChargeResource::getUrl()],
            ['label' => __('Subscription charges'), 'url' => SubscriptionChargeResource::getUrl()],
            ['label' => __('Users'), 'url' => UserResource::getUrl()],
            ['label' => __('Retailers'), 'url' => RetailerMetaResource::getUrl()],
            ['label' => __('Withdrawals'), 'url' => RetailerWithdrawalsPage::getUrl()],
            ['label' => __('Retailer earnings'), 'url' => RetailerEarningResource::getUrl()],
            ['label' => __('Tickets'), 'url' => TicketResource::getUrl()],
            ['label' => __('Languages'), 'url' => LanguageResource::getUrl()],
            ['label' => __('Enterprise onboarding'), 'url' => EnterpriseOnboardingResource::getUrl()],
            ['label' => __('Settings'), 'url' => SiteSettingsPage::getUrl()],
            ['label' => __('Posts'), 'url' => PostResource::getUrl()],
            ['label' => __('Pages'), 'url' => PageResource::getUrl()],
            ['label' => __('Post categories'), 'url' => PostCategoryResource::getUrl()],
            ['label' => __('Menu builder'), 'url' => MenuBuilder::getUrl()],
            ['label' => __('Menus'), 'url' => CmsMenuResource::getUrl()],
            ['label' => __('FAQ'), 'url' => FaqResource::getUrl()],
            ['label' => __('Changelog'), 'url' => ChangelogResource::getUrl()],
            ['label' => __('Plugins'), 'url' => PaymentMethodAccessResource::getUrl()],
            ['label' => __('Payment badges'), 'url' => PaymentBadgeResource::getUrl()],
            ['label' => __('Server status'), 'url' => ServerStatusPage::getUrl()],
        ];
    }
}
