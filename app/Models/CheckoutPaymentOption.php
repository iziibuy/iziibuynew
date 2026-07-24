<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutPaymentOption extends Model
{
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'acquirers' => 'array',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];
}
