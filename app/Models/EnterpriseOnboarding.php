<?php

namespace App\Models;

use App\Models\Traits\HasMeta;
use App\Services\Elavon\ElavonOnboardingPromo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnterpriseOnboarding extends Model
{
    use HasFactory, HasMeta;

    public const SUBSCRIPTION_METHOD_ELAVON = 'elavon';

    public const ELAVON_RESUBSCRIPTION_MESSAGE = 'Please resubscribe with Elavon to reactivate this enterprise account.';

    public const EXPORT_META_FIELDS = [
        'name',
        'company_name',
        'logo',
        'cover',
        'contact_email',
        'contact_phone',
        'city',
        'street',
        'post_code',
        'title',
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
        'customerDetails',
        'trading',
        'partner',
        'productId',
        'needKYC',
        'elavon_merchant_alias',
        'elavon_public_key',
        'elavon_secret_key',
        'gateway_contract_signed',
        'selected_payment_methods',
        'surfboard_webKybUrl',
        'surfboard_terminalId',
        'surfboard_application_id',
        'surfboard_application_status',
        'surfboard_merchantId',
        'surfboard_storeId',
        'surfboard_applicationStatus',
    ];

    protected $casts = ['last_paid_at' => 'datetime'];

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

    public function canProcessPayments(): bool
    {
        return ! $this->requiresElavonResubscription() && (bool) ($this->attributes['status'] ?? false);
    }

    public function signupFee(): float
    {
        if (ElavonOnboardingPromo::isFreeSubscriptionPeriod()) {
            return ElavonOnboardingPromo::PROMO_SIGNUP_FEE;
        }

        return (float) $this->getSubscriptionFee();
    }

    protected $meta_attributes = self::EXPORT_META_FIELDS;

    protected $guarded = [];

    /**
     * Safe string for UI (Filament tables, mail) when {@see companyAddress} returns a decoded object or plain text.
     */
    public static function companyAddressAsString(mixed $address): string
    {
        if ($address === null) {
            return '';
        }

        if (is_string($address)) {
            return $address;
        }

        if (is_array($address)) {
            $address = (object) $address;
        }

        if (! is_object($address)) {
            return '';
        }

        $line1 = (string) ($address->street ?? '');
        $zipCity = trim(implode(' ', array_filter([
            isset($address->zip) ? (string) $address->zip : '',
            isset($address->city) ? (string) $address->city : '',
        ], fn (string $p): bool => $p !== '')));
        $country = isset($address->country) ? (string) $address->country : '';

        $parts = array_filter([$line1, $zipCity, $country], fn (string $p): bool => $p !== '');

        return implode(', ', $parts);
    }

    public function establishmentFee(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => ((int) ($value ?? 0)) / 100,
            set: fn (int|float $value): int => (int) round(((float) $value) * 100)
        );
    }

    public function subscription()
    {
        return $this->morphOne(Subscription::class, 'subscribable');
    }

    public function getSubscriptionFee()
    {
        $establishmentCost = 0;
        if ($this->is_establishment == 0) {
            $establishmentCost = $this->establishment_fee;
        }

        return ($this->fee + $establishmentCost) + (($this->fee + $establishmentCost) * (25 / 100));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function companyAddress(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): mixed {
                if ($value === null || $value === '') {
                    return null;
                }

                if (! is_string($value)) {
                    return $value;
                }

                $decoded = json_decode($value);
                if (json_last_error() === JSON_ERROR_NONE && (is_object($decoded) || is_array($decoded))) {
                    return is_array($decoded) ? (object) $decoded : $decoded;
                }

                return $value;
            },
            set: function (mixed $value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }

                if (is_string($value)) {
                    return $value;
                }

                return json_encode($value);
            }
        );
    }

    public function addressFull(): Attribute
    {
        return Attribute::make(get: function (): string {
            $addr = $this->company_address;

            if ($addr === null) {
                return '';
            }

            if (is_string($addr)) {
                return $addr;
            }

            if (! is_object($addr)) {
                return '';
            }

            return trim(
                (($addr->street ?? '').' '.
                    ($addr->zip ?? '').' '.
                    ($addr->city ?? ''))
            );
        });
    }
}
