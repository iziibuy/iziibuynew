<?php

namespace App\Models;

use App\Support\ExternalPaymentAcquirer;
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

    protected static function booted(): void
    {
        static::creating(function (ExternalOrder $model): void {
            if (blank($model->platform_percentage) && filled($model->payment_method_access_id)) {
                $model->platform_percentage = (float) PaymentMethodAccess::query()
                    ->whereKey($model->payment_method_access_id)
                    ->value('platform_commission');
            }

            $model->platform_percentage = (float) $model->platform_percentage;
            $model->platform_amount = round((float) $model->amount * $model->platform_percentage / 100, 2);
        });
    }

    public function paymentMethodAccess(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodAccess::class);
    }

    public function paymentApi(): BelongsTo
    {
        return $this->belongsTo(PaymentApi::class, 'api_id');
    }

    /**
     * @return 'elavon'|'surfboard'|'unknown'
     */
    public function resolvedPaymentMethod(): string
    {
        return ExternalPaymentAcquirer::forOrder($this);
    }
}
