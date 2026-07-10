<?php

namespace App\Models;

use App\Models\Traits\HasMeta;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Iziibuy;

class PaymentMethodAccess extends Model
{
    use HasFactory, HasMeta;

    public const SUBSCRIPTION_METHOD_ELAVON = 'elavon';

    public const ELAVON_RESUBSCRIPTION_MESSAGE = 'Please resubscribe with Elavon to reactivate this payment method.';

    protected $guarded = [];

    protected $casts = ['last_paid_at' => 'datetime'];

    protected $meta_attributes = [
        'name',

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
        'date',
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
        'fallback_payment_method',
        'sms_text',
        'tax_percentage',
        'booking_create_page_title',
        'currency',
        'booking_phone_prefix',
    ];

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

    public function companyAddress(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?object {
                if ($value === null || $value === '') {
                    return null;
                }

                $decoded = json_decode($value);

                return is_object($decoded) ? $decoded : null;
            },
            set: fn ($value) => $value === null ? null : json_encode($value)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function companyAddressToFormState(mixed $address): array
    {
        if ($address === null) {
            return [];
        }

        if (is_string($address)) {
            $decoded = json_decode($address, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($address)) {
            return (array) $address;
        }

        return is_array($address) ? $address : [];
    }

    public static function companyAddressAsString(mixed $address): string
    {
        if ($address === null || $address === '') {
            return '';
        }

        if (is_string($address)) {
            $decoded = json_decode($address);

            if (is_object($decoded)) {
                $address = $decoded;
            } else {
                return $address;
            }
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
        ], fn (string $part): bool => $part !== '')));
        $country = isset($address->country) ? (string) $address->country : '';

        $parts = array_filter([$line1, $zipCity, $country], fn (string $part): bool => $part !== '');

        return implode(', ', $parts);
    }

    public function update(array $attributes = [], array $options = [])
    {
        if (! $this->exists) {
            return false;
        }

        return $this->fill($attributes)->save($options);
    }

    public function addressFull(): Attribute
    {
        return Attribute::make(get: fn () => self::companyAddressAsString($this->company_address));
    }

    public function fee(): float
    {
        $base = (float) ($this->attributes['fee'] ?? 0);
        if ($base <= 0) {
            $base = (float) setting('payment.payment_method_fee', 0);
        }

        if ($base <= 0) {
            return 0.0;
        }

        $prorated = Iziibuy::needToCharge($base);

        return $prorated + ($prorated * 0.25);
    }

    public function subscription()
    {
        return $this->morphOne(Subscription::class, 'subscribable');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentapis()
    {
        return $this->hasMany(PaymentApi::class, 'payment_method_access_id');
    }
}
