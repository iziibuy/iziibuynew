<?php

namespace App\Models;

use App\Constants\Constants;
use App\Enterprise\Permissions;
use App\Models\Traits\HasMeta;
use App\Models\Traits\LegacyVoyagerGetsTranslatedAttribute;
use App\Services\Checkout\CheckoutPaymentOptionCatalog;
use App\Services\Elavon\ElavonOnboardingPromo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Iziibuy;
use Spatie\Translatable\HasTranslations;

class Shop extends Model
{
    use HasFactory, HasMeta, HasTranslations, LegacyVoyagerGetsTranslatedAttribute {
        getTranslation as protected spatieGetTranslation;
    }

    public const SUBSCRIPTION_METHOD_ELAVON = 'elavon';

    public const ELAVON_RESUBSCRIPTION_MESSAGE = 'Please resubscribe with Elavon to reactivate this account.';

    protected $guarded = [];

    protected $translatable = ['terms'];

    protected $casts = ['paid_at' => 'datetime', 'previous_retailer_suspended_at' => 'datetime', 'retailer_joined_at' => 'datetime'];

    protected $meta_attributes = [
        'title',
        'name',
        'company_name',
        'logo',
        'cover',
        'contact_email',
        'contact_phone',
        'default_language',
        'company_registration',
        'city',
        'street',
        'post_code',
        'shop_color',
        'header_color',
        'menu_color',
        'top_menu_hover_color',
        'menu_hover_color',
        'top_header_color',
        'self_checkout',
        'self_checkout_pin',
        'sell_digital_product',
        'description',
        'quickpay_api_key',
        'quickpay_secret_key',
        'two_api_key',
        'two_secret_key',
        'elavon_merchant_alias',
        'elavon_public_key',
        'elavon_secret_key',
        'menu',
        'default_package_option',
        'self_checkout_pin',
        'security_key',
        'scanner_active',
        'scanner_device',
        'force_register',
        'package_validity',
        'inactive_days',
        'socials',
        'order_pending_hours',
        'free_shiping_after',
        'shipping_force_register',
        'show_categories_on_home',
        'company_url',
        'card_holder_name',
        'card_number',
        'expiration_month',
        'expiration_year',
        'ccv',
        'contactPerson',
        'businessAddress',
        'comapny_address',
        'ownership',
        'orgNumber',
        'foundationDate',
        'businessDescription',
        'creditCardTurnover',
        'avgTransactionValue',
        'cardHolderPresent',
        'mailPhoneOrder',
        'internet',
        'gender',
        'dob',
        'share',
        'ceo',
        'privateAddress',
        'otherNationality',
        'country',
        'mobileNumber',
        'privateEmail',
        'idNumber',
        'issueDate',
        'expiryDate',
        'nationality',
        'bankName',
        'accountHolderName',
        'accountNumber',
        'selectedUserName',
        'preferredUsername',
        'userEmail',
        'userPhoneNumber',
        'fullNameTitle',
        'date',
        'signature',
        'elavon_payment_setup',
        'elavon_details_verified_by_shop',
        'customer_profile',
        'authrized',
        'financial',
        'report',
        'ip_address',
        'date',
        'customerDetails',
        'trading',
        'partner',
        'productId',
        'needKYC',
        'footerPaymentMethod',
        'top_header_language_text_color',
        'top_header_language_text_hover_color',
        'top_header_search_bar_text_color',
        'top_header_search_bar_hover_color',
        'top_header_search_bar_bg_color',
        'top_header_search_btn_text_color',
        'top_header_search_btn_hover_color',
        'top_header_search_btn_bg_color',
        'footer_text_hover_color',
        'footer_text_color',
        'footer_bg_color',
        'buy_btn_hover_color',
        'buy_btn_text_color',
        'shop_bottom_color',
        'buy_btn_bg_color',
        'qr_code_option',
        'site_mode',
        'gateway_contract_signed',
        'fallback_payment_method',
        'checkout_payment_options',
        'selected_payment_methods',
        'surfboard_terminalId',
        'surfboard_merchantId',
        'surfboard_storeId',
        'dintero_account_id',
        'dintero_onboarding_url',
        'dintero_onboarding_status',
    ];

    protected static function booted(): void
    {
        static::saving(function (Shop $shop): void {
            if (filled($shop->elavon_plan_id) || filled($shop->elavon_subscription_id)) {
                $shop->subscriptionMethod = self::SUBSCRIPTION_METHOD_ELAVON;
            }
        });
    }

    public function getTranslation(string $key, string $locale, bool $useFallbackLocale = true): mixed
    {
        $translation = $this->spatieGetTranslation($key, $locale, $useFallbackLocale);

        if (filled($translation) || ! $this->isTranslatableAttribute($key)) {
            return $translation;
        }

        $raw = $this->getAttributes()[$key] ?? null;

        if (! is_string($raw) || blank($raw)) {
            return $translation;
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $raw;
        }

        if (is_array($decoded)) {
            foreach ($decoded as $value) {
                if (filled($value)) {
                    return $value;
                }
            }
        }

        return $translation;
    }

    public function status(): Attribute
    {
        return Attribute::make(
            get: fn ($value): bool => $this->requiresElavonResubscription() ? false : (bool) $value
        );
    }

    public function needsElavonResubscription(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->requiresElavonResubscription()
        );
    }

    public function elavonResubscriptionMessage(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->requiresElavonResubscription()
                ? self::ELAVON_RESUBSCRIPTION_MESSAGE
                : null
        );
    }

    public function requiresElavonResubscription(): bool
    {
        return ! $this->hasElavonSubscriptionMethod();
    }

    public function hasElavonSubscriptionMethod(): bool
    {
        return strtolower((string) ($this->attributes['subscriptionMethod'] ?? '')) === self::SUBSCRIPTION_METHOD_ELAVON;
    }

    public function hasArea()
    {
        return true;
    }

    public function locations(): Attribute
    {
        return Attribute::make(get: fn ($value) => (array) json_decode($value));
    }

    public function retailerJoinedAt(): Attribute
    {
        return Attribute::make(get: fn ($value) => $this->attributes['retailer_joined_at'] ?? $this->attributes['created_at']);
    }

    public function previousRetailerSuspendedAt(): Attribute
    {
        return Attribute::make(get: function ($value) {

            if ($this->attributes['previous_retailer_suspended_at']) {

                return $this->attributes['previous_retailer_suspended_at'];
            } else {
                if ($this->retailer && @$this->retailer->retailer && @$this->retailer->retailer->status == true) {

                    return now();
                }
            }

            return $this->attributes['created_at'];
        });
    }

    public function address(): Attribute
    {
        return Attribute::make(get: fn () => $this->street.' '.$this->post_code.' '.$this->city);
    }

    public function shippingForceRegister(): Attribute
    {
        return Attribute::make(get: fn ($value) => $this->shipping_force_register ? $this->shipping_force_register : 'No');
    }

    public function links(): Attribute
    {
        return Attribute::make(get: fn ($value) => empty((array) json_decode($this->socials)) ? [['platform' => '', 'url' => '', 'position' => 'footer']] : (array) json_decode($this->socials));
    }

    public function hasRetailerPricing()
    {
        return false;
    }

    public function retailer()
    {
        return $this->belongsTo(User::class, 'retailer_id');
    }

    public function prevRetailer()
    {
        return $this->belongsTo(User::class, 'retailer_id');
    }

    public function resources()
    {
        return $this->hasMany(Resource::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function defaultCurrency(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?? 'NOK',
            set: fn ($value) => strtoupper($value)
        );
    }

    public function getMenusAttribute()
    {
        $shop_menu_items = json_decode($this->menu);
        $original_menu_items = Constants::FRONTEND_MENU;
        if ($shop_menu_items) {
            foreach ($shop_menu_items as $key => $value) {
                if (array_key_exists($key, $original_menu_items)) {
                    $original_menu_items[$key] = $value;
                }
            }
        }

        return $original_menu_items;
    }

    public function defaultoption(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->options) {
                    if ($this->default_package_option) {
                        return Packageoption::find($this->default_package_option);
                    }

                    return $this->options->first();
                }

                return null;
            }
        );
    }

    public function ensureDefaultPackageOption(): Packageoption
    {
        if ($this->default_package_option) {
            $defaultOption = Packageoption::query()
                ->where('shop_id', $this->id)
                ->find($this->default_package_option);

            if ($defaultOption) {
                return $defaultOption;
            }
        }

        $defaultOption = $this->options()->first();

        if (! $defaultOption) {
            $defaultOption = $this->options()->create([
                'title' => 'Standard',
                'details' => 'Standard booking option',
                'minutes' => 60,
                'buffer' => 0,
            ]);
        }

        $this->createMeta('default_package_option', $defaultOption->id);

        return $defaultOption;
    }

    public function getSelfCheckoutAttribute()
    {

        return $this->self_checkout == '1' && Permissions::check('kiosk', 'active') ? true : false;
    }

    public function hasSelfCheckout()
    {

        if (! $this->selfCheckout) {
            return false;
        }
        if (Cookie::get('kiosk-'.$this->user_name) != 'active') {
            return false;
        }

        return true;
    }

    public function checkDefaultCurrency($currency)
    {

        return $this->default_currency === $currency ? true : false;
    }

    public function checkCurrency($currency)
    {
        $array = json_decode($this->currencies) ?? ['NOK'];

        return in_array($currency, $array);
    }

    public function boxes()
    {
        return $this->hasMany(Box::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function levels()
    {
        return $this->hasMany(Level::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    public function options()
    {
        return $this->hasMany(Packageoption::class);
    }

    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    public function shippings()
    {
        return $this->hasMany(Shipping::class);
    }

    public function priceGroups()
    {
        return $this->hasMany(PriceGroup::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function getEstablishmentCostAttribute($value)
    {
        if (isset($value)) {
            return $value / 100;
        } else {
            return null;
        }
    }

    public function setEstablishmentCostAttribute($value)
    {
        if (isset($value)) {
            $this->attributes['establishment_cost'] = $value * 100;
        } else {
            $this->attributes['establishment_cost'] = null;
        }
    }

    public function getMonthlyCostAttribute($value)
    {
        if (isset($value)) {
            return $value / 100;
        }
    }

    public function setMonthlyCostAttribute($value)
    {
        if (isset($value)) {
            $this->attributes['monthly_cost'] = $value * 100;
        } else {
            $this->attributes['monthly_cost'] = null;
        }
    }

    public function setServiceEstablishmentCostAttribute($value)
    {
        $this->attributes['service_establishment_cost'] = $value * 100;
    }

    public function setServiceMonthlyFeeAttribute($value)
    {
        $this->attributes['service_monthly_fee'] = $value * 100;
    }

    public function getServiceMonthlyFeeAttribute($value)
    {
        return $value / 100;
    }

    public function getServiceEstablishmentCostAttribute($value)
    {
        return $value / 100;
    }

    public function sellingLocations()
    {
        switch ($this->selling_location_mode) {
            case '0':
                return Constants::COUNTRIES;

            case '1':
                return $this->locations;
            case '2':
                return [...array_diff(Constants::COUNTRIES, $this->locations)];
            default:
                return Constants::COUNTRIES;
        }
    }

    public function getPerUserFeeAttribute($value)
    {
        if (isset($value)) {
            return $value / 100;
        }
    }

    public function setPerUserFeeAttribute($value)
    {
        if (isset($value)) {
            $this->attributes['per_user_fee'] = $value * 100;
        } else {
            $this->attributes['per_user_fee'] = null;
        }
    }

    public function establishedFee()
    {
        return $this->establishment_cost;
    }

    public function monthlyFee()
    {

        $cost = $this->monthly_cost;

        return Iziibuy::needToCharge($cost) + $this->perUserFee();
    }

    /**
     * VAT rate (%) for platform subscription fees. Uses the shop's tax when set,
     * otherwise falls back to the global PAYMENT_REGISTRATION_TAX setting.
     */
    public function registrationTax(): float
    {
        if (array_key_exists('tax', $this->attributes) && $this->attributes['tax'] !== null) {
            return (float) $this->attributes['tax'];
        }

        return (float) config('settings.payment.registration_tax', 0);
    }

    public function getTax($amount)
    {
        return $amount * ($this->registrationTax() / 100);
    }

    public function singleUserCost()
    {
        return $this->per_user_fee;
    }

    public function perUserFee()
    {
        if ($this->users->count() > 1) {
            $cost = $this->singleUserCost();
        } else {
            $cost = 0;
        }

        return Iziibuy::needToCharge($cost);
    }

    public function singleUserNeedToCharge()
    {
        $cost = $this->per_user_fee;

        return Iziibuy::needToCharge($cost);
    }

    public function openingFee()
    {
        $amount = ($this->establishedFee() + $this->monthlyFee() - (float) $this->discount());
        $final = $amount + ($amount * ($this->registrationTax() / 100));

        return $final;
    }

    public function openingTax()
    {

        $amount = ($this->establishedFee() + $this->monthlyFee() - (float) $this->discount());

        return $amount * ($this->registrationTax() / 100);
    }

    public function subscriptionFeeFull()
    {

        $amount = 0;
        if ($this->establishment == 0) {
            $amount += $this->establishment_cost;
        }
        $amount += $this->monthly_cost;
        $amount += $this->perUserFee();
        $amount += $amount * ($this->registrationTax() / 100);

        return $amount;
    }

    /**
     * Monthly recurring amount for Elavon plan `total` (excludes one-time establishment fee).
     */
    public function elavonRecurringSubscriptionAmount(): float
    {
        if ($this->users->count() > 1) {
            $perUserCost = $this->singleUserCost();
        } else {
            $perUserCost = 0;
        }

        $amount = $this->monthly_cost + $perUserCost - (float) $this->discount();

        return $amount + ($amount * ($this->registrationTax() / 100));
    }

    public function usesElavonNativeSubscription(): bool
    {
        return $this->hasElavonSubscriptionMethod() && filled($this->elavon_subscription_id);
    }

    public function usesElavonAppManagedSubscription(): bool
    {
        return $this->hasElavonSubscriptionMethod()
            && filled($this->subscription_id)
            && ! $this->usesElavonNativeSubscription();
    }

    public function usesElavonSandbox(): bool
    {
        return (bool) ($this->attributes['is_demo'] ?? false);
    }

    public function subscriptionFee()
    {
        if (ElavonOnboardingPromo::isFreeSubscriptionPeriod()) {
            return (int) ElavonOnboardingPromo::PROMO_SIGNUP_FEE;
        }

        if ($this->paid_at == null && @$this->paid_at?->isCurrentMonth() == false) {

            if ($this->establishment == 1) {
                $amount = $this->monthlyFee() - (float) $this->discount();
            } else {
                $amount = ($this->establishedFee() + $this->monthlyFee()) - (float) $this->discount();
            }

            return $amount + $this->tax();
        } elseif ($this->paid_at != null && @$this->paid_at?->isCurrentMonth() == false) {
            if ($this->users->count() > 1) {
                $perusercost = $this->singleUserCost();
            } else {
                $perusercost = 0;
            }
            if ($this->establishment == 1) {
                $amount = $this->monthly_cost + $perusercost - (float) $this->discount();
            } else {
                $amount = ($this->establishment_cost + $this->monthly_cost + $this->perusercost - (float) $this->discount());
            }

            return $amount + $this->tax();
        } else {
            return 0;
        }
    }

    public function showScanner()
    {

        if ($this->scanner_active != 1) {
            return false;
        }

        return true;
    }

    public function subscriptionFeeDetails()
    {
        $lastDayofMonth = Carbon::now()->endOfMonth();
        $left = Carbon::now()->diffInDays($lastDayofMonth) + 1;

        return [
            'charged_at' => now()->format('d M,Y'),
            'last_day_of_month' => Carbon::now()->endOfMonth()->format('d M,Y'),
            'days_before_the_months_end' => $left,
            'shop' => [
                'id' => $this->id,
                'name' => $this->user_name,
                'managers' => $this->users()->where('role_id', 4)->count(),
                'establishment' => (bool) $this->establishment,
                'fees' => [
                    'per_manager_cost' => $this->per_user_fee,
                    'monthly_cost' => $this->monthly_cost,
                    'esatblisment_cost' => $this->establishment ? 0 : $this->establishedFee(),
                ],
            ],
            'need_to_pay' => [
                'managers_cost' => $this->perUserFee(),
                'monthly_cost' => $this->monthlyFee() - $this->perUserFee(),
                'esatblisment_cost' => $this->establishment ? 0 : $this->establishedFee(),
                'tax' => $this->tax(),
            ],
            'discount' => $this->discount(),
            'total' => $this->subscriptionFee(),
        ];

        return
            'Charged At ='.now()->format('d M,Y').'<br>'.
            'Days Left ='.$left.'<br>'.
            'Managers Count ='.(int) $this->users()->where('role_id', 4)->count().'<br>'.
            'Manager Fee ='.(float) $this->per_user_fee.'and have to pay <br>'.(float) $this->singleUserNeedToCharge().'*'.(int) $this->users()->where('role_id', 4)->count().'='.(float) $this->perUserFee().'<br>'.
            'Monthlt Fee ='.(float) $this->monthly_cost.'and have to pay'.(float) $this->monthlyFee().(float) $this->perUserFee().'<br>'.
            'Establishment Cost ='.(float) $this->establishedFee().'<br>'.
            'Tax ='.(float) $this->tax().'<br>'.
            'Discount ='.(float) $this->discount().'<br>'.
            'Total ='.(float) $this->subscriptionFee().'<br>';
    }

    public function tax()
    {
        if ($this->establishment == 1) {
            $amount = $this->monthlyFee() - $this->discount();
        } else {
            $amount = ($this->establishedFee() + $this->monthlyFee()) - $this->discount();
        }

        return $amount * ($this->registrationTax() / 100);
    }

    public function discount()
    {
        if (session()->has('discount_shop')) {
            return session('discount_shop');
        }

        return 0;
    }

    public function ServiceMonthlyCost()
    {

        return Iziibuy::needToCharge($this->service_monthly_fee);
    }

    public function ServiceEstablismentCost()
    {
        return $this->service_establishment_cost;
    }

    public function ServiceSubscriptionFee($tax = true)
    {

        $establishment_fee = $this->service_establishment ? 0 : $this->service_establishment_cost;
        $monthly_fee = $this->ServiceMonthlyCost();
        $total = $establishment_fee + $monthly_fee;
        if ($tax) {
            return $total + $this->getTax($total);
        } else {
            return $total;
        }
    }

    public function charges()
    {
        return $this->hasMany(Charge::class);
    }

    public function scopeRelation($query)
    {
        return $query->with('metas');
    }

    public function mylanguages()
    {
        return $this->belongsToMany(Language::class, 'language_shop')->withPivot('english', 'spanish', 'norwegian')->withTimestamps();
    }

    public function checkout_payment_methods()
    {
        return $this->enabledCheckoutPaymentOptions();
    }

    /**
     * Flat list of enabled checkout options for this shop.
     *
     * @return array<string, array{key: string, label: string, icon: string, acquirer: string}>
     */
    public function enabledCheckoutPaymentOptions(): array
    {
        $catalog = app(CheckoutPaymentOptionCatalog::class)->active();
        $config = $this->checkoutPaymentOptionsConfig();
        $enabled = [];

        foreach ($catalog as $key => $option) {
            $shopOption = $config[$key] ?? null;

            if (! is_array($shopOption) || empty($shopOption['enabled'])) {
                continue;
            }

            $acquirer = (string) ($shopOption['acquirer'] ?? '');
            if ($acquirer === '' || ! in_array($acquirer, $option['acquirers'], true)) {
                continue;
            }

            $enabled[$key] = [
                'key' => $key,
                'label' => $option['label'],
                'icon' => (string) ($option['icon'] ?? $key),
                'acquirer' => $acquirer,
            ];
        }

        return $enabled;
    }

    /**
     * @return array<string, array{enabled: bool, acquirer: string|null}>
     */
    public function checkoutPaymentOptionsConfig(): array
    {
        $raw = $this->checkout_payment_options;
        $fromMeta = null;

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $fromMeta = $this->normalizeCheckoutPaymentOptionsConfig($decoded);
            }
        } elseif (is_array($raw)) {
            $fromMeta = $this->normalizeCheckoutPaymentOptionsConfig($raw);
        }

        if (is_array($fromMeta) && $this->checkoutPaymentOptionsConfigHasEnabled($fromMeta)) {
            return $fromMeta;
        }

        return $this->defaultCheckoutPaymentOptionsConfigFromGateways();
    }

    /**
     * @param  array<string, array{enabled: bool, acquirer: string|null}>  $config
     */
    protected function checkoutPaymentOptionsConfigHasEnabled(array $config): bool
    {
        foreach ($config as $row) {
            if (! empty($row['enabled']) && filled($row['acquirer'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the acquirer for a checkout payment option key (visa, vipps, …).
     */
    public function acquirerForCheckoutOption(?string $optionKey): ?string
    {
        if ($optionKey === null || $optionKey === '') {
            return null;
        }

        $enabled = $this->enabledCheckoutPaymentOptions();

        return $enabled[$optionKey]['acquirer'] ?? null;
    }

    /**
     * Acquirers currently selected across enabled checkout options.
     *
     * @return list<string>
     */
    public function selectedCheckoutAcquirers(): array
    {
        return array_values(array_unique(array_map(
            fn (array $option): string => $option['acquirer'],
            $this->enabledCheckoutPaymentOptions()
        )));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, array{enabled: bool, acquirer: string|null}>
     */
    public function normalizeCheckoutPaymentOptionsConfig(array $config): array
    {
        $catalog = app(CheckoutPaymentOptionCatalog::class)->all();
        $normalized = [];

        foreach ($catalog as $key => $option) {
            $row = $config[$key] ?? [];
            if (! is_array($row)) {
                $row = [];
            }

            $acquirer = isset($row['acquirer']) && is_string($row['acquirer']) && $row['acquirer'] !== ''
                ? $row['acquirer']
                : null;

            if ($acquirer !== null && ! in_array($acquirer, $option['acquirers'], true)) {
                $acquirer = $option['acquirers'][0] ?? null;
            }

            $normalized[$key] = [
                'enabled' => (bool) ($row['enabled'] ?? false),
                'acquirer' => $acquirer,
            ];
        }

        return $normalized;
    }

    /**
     * Legacy fallback when shop has no checkout_payment_options meta yet.
     *
     * @return array<string, array{enabled: bool, acquirer: string|null}>
     */
    protected function defaultCheckoutPaymentOptionsConfigFromGateways(): array
    {
        $gateways = array_values(array_filter(explode(',', (string) $this->paymentMethod)));
        $catalog = app(CheckoutPaymentOptionCatalog::class)->active();
        $config = [];

        foreach ($catalog as $key => $option) {
            $preferred = null;

            foreach ($option['acquirers'] as $acquirer) {
                if (in_array($acquirer, $gateways, true)) {
                    $preferred = $acquirer;
                    break;
                }
            }

            // Cards historically defaulted to elavon/quickpay; wallets to surfboard/dintero.
            if ($preferred === null && in_array($key, ['visa', 'mastercard', 'amex'], true)) {
                foreach (['elavon', 'quickpay', 'surfboard'] as $acquirer) {
                    if (in_array($acquirer, $gateways, true) && in_array($acquirer, $option['acquirers'], true)) {
                        $preferred = $acquirer;
                        break;
                    }
                }
            }

            $config[$key] = [
                'enabled' => $preferred !== null,
                'acquirer' => $preferred,
            ];
        }

        return $config;
    }

    public function hasPaymentGateway($gateway)
    {
        return in_array($gateway, explode(',', (string) $this->paymentMethod), true);
    }
}
