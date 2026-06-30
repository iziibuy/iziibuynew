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

    public function getDomainAttribute()
    {
        return @json_decode($this->payment_details)->enterprise->domain ?? 'N/A';
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
        return Attribute::make(get: fn () => $this->attributes['charge_details'] ? json_decode($this->attributes['charge_details'])->metadata->last4 : 'N/A');
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
