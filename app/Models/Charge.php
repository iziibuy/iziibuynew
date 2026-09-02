<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    use HasFactory;

    protected $additional_attributes = ['lastFour'];
    protected $guarded = [];


    public function getAmountAttribute()
    {
        return $this->attributes['amount'] / 100;
    }
    public function setAmountAttribute($value)
    {
        return $this->attributes['amount'] = $value * 100;
    }
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function getCommentAttribute($value)
    {
        switch ($value) {
            case 'Subscription fee':
                return __('words.charge_subscription_fee');
            case 'adding new manager':
                return __('words.adding_new_manager');
            case 'Monthly subscription fee':
                return __('words.monthly_subscription_fee');
            case 'Service subscription':
                return __('words.charge_service_subscription_fee');
            default:
                return $value;
        }
    }

    public function scopeUnresolved($query)
    {
        $query->where('payment_type', 'Unresolved');
    }
    public function paymentBodyObj(): Attribute
    {
        return Attribute::make(get: fn ($value) => $this->attributes['payment_body'] ? json_decode($this->attributes['payment_body'])[0] : null);
    }

    public function lastFour(): Attribute
    {
      
        return Attribute::make(get: fn ($value) => $this->paymentBodyObj ? $this->paymentBodyObj->metadata->last4 : 'N/A');
    }
}
