<?php

namespace App\Models;

use App\Payment\Elavon\CheckoutJsTheme;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentApi extends Model
{
    use HasFactory;

    protected $guarded = [];

    public const ELAVON_LINK_MODE_HOSTED = 'hosted';

    public const ELAVON_LINK_MODE_CHECKOUTJS = 'checkoutjs';

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'is_subscription' => 'boolean',
            'checkoutjs_appearance' => 'array',
        ];
    }

    public function isSubscriptionButton(): bool
    {
        return (bool) $this->is_subscription;
    }

    public function usesElavonCheckoutJs(): bool
    {
        return ($this->elavon_link_mode ?? self::ELAVON_LINK_MODE_HOSTED) === self::ELAVON_LINK_MODE_CHECKOUTJS;
    }

    public function checkoutJsTheme(): CheckoutJsTheme
    {
        return CheckoutJsTheme::fromApi($this);
    }

    public function externalSubscriptions(): HasMany
    {
        return $this->hasMany(ExternalSubscription::class, 'api_id');
    }

    public static function generateKey(): string
    {
        return (string) Str::ulid();
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentApi $paymentApi): void {
            if (blank($paymentApi->key)) {
                $paymentApi->key = self::generateKey();
            }

            if ($paymentApi->status === null) {
                $paymentApi->status = true;
            }
        });
    }

    public function paymentMethodAccess(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodAccess::class);
    }
}
