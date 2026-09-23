<?php

namespace App\Models;

use App\Models\Traits\HasMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, HasMeta;

    protected $guarded = [];

    protected $casts = ['paid_at' => 'datetime'];

    protected $meta_attributes = [
        'first_name',
        'last_name',
        'email',
        'address',
        'city',
        'post_code',
        'phone',
        'state',
        'is_vcard',
        'details',
        'company_country_prefix',
        'company_name',
        'company_id',
        'renew',
        'trainer',
        'package',
        'package_title',
        'is_digital',
        'create_a_account',
        'elavon_transaction_id',
        'surfboard_transaction_id',

    ];

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity', 'price', 'variation');
    }

    public function shippingMethod()
    {
        return $this->belongsTo(Shipping::class, 'shipping_method');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function status($key = 0)
    {
        return [
            0 => __('words.status_pending'), // Awaiting payment
            1 => __('words.status_paid'), // Paid
            2 => __('words.status_shipped'), // Sent
            3 => __('words.status_canceled'), // Cancels
            4 => __('words.not_delivered'), // //not delivered
            5 => __('words.delivered'), // delivered
        ][$this->status];
    }

    public function get_currency()
    {
        return 'NOK';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function maxRefund()
    {
        return $this->total - $this->refund;
    }

    public function scopeRelation($query)
    {
        return $query->with(['shop']);
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (blank($order->uuid)) {
                $order->uuid = (string) Str::ulid();
            }
        });

        static::addGlobalScope('doNotShowCanceledOrder', function (Builder $builder) {
            $builder->where('status', '!=', 3);
        });
    }

    public function taxPercentage()
    {
        $baseTotal = $this->total - $this->tax;

        return ($this->tax / $baseTotal) * 100;
    }
}
