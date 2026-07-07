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

    public function companyAddress(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => json_decode($value),
            set: fn ($value) => json_encode($value)
        );
    }

    public function update(array $attributes = [], array $options = [])
    {
        unset($attributes['key']);
        if (! $this->exists) {
            return false;
        }

        return $this->fill($attributes)->save($options);
    }

    public function addressFull(): Attribute
    {
        return Attribute::make(get: fn () => $this->company_address->street.' '.$this->company_address->zip.' '.$this->company_address->city);
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
