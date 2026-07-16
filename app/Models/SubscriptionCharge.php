<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionCharge extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $additional_attributes = ['domain'];

    public function getDomainAttribute(): string
    {
        if (blank($this->payment_details)) {
            return 'N/A';
        }

        $decoded = json_decode((string) $this->payment_details, true);
        if (! is_array($decoded)) {
            return 'N/A';
        }

        return (string) (
            data_get($decoded, 'enterprise.domain')
            ?? data_get($decoded, 'payment_method_access.domain')
            ?? data_get($decoded, 'shop.domain')
            ?? 'N/A'
        );
    }

    public function getAmountAttribute()
    {
        return $this->attributes['amount'] / 100;
    }

    public function setAmountAttribute($value)
    {
        return $this->attributes['amount'] = $value * 100;
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function last4(): Attribute
    {
        return Attribute::make(get: function (): string {
            if (blank($this->attributes['charge_details'] ?? null)) {
                return 'N/A';
            }

            $decoded = json_decode((string) $this->attributes['charge_details'], true);
            if (! is_array($decoded)) {
                return 'N/A';
            }

            return (string) (
                data_get($decoded, 'metadata.last4')
                ?? data_get($decoded, 'last4')
                ?? 'N/A'
            );
        });
    }

    public function scopeEnterpriseOnly($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('quickpay_order_id')
                ->orWhereNotNull('elavon_transaction_id');
        })->whereHas('subscription', function ($q) {
            $q->where('subscribable_type', 'App\Models\Enterprise');
        });
    }
}
