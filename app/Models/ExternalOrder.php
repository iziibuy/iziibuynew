<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalOrder extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_at' => 'datetime',
            'items' => 'array',
        ];
    }

    public function paymentMethodAccess(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodAccess::class);
    }

    public function paymentApi(): BelongsTo
    {
        return $this->belongsTo(PaymentApi::class, 'api_id');
    }
}
