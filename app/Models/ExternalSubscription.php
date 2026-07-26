<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ExternalSubscription extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'interval_days' => 'integer',
            'paid_at' => 'datetime',
            'next_charge_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExternalSubscription $subscription): void {
            if (blank($subscription->uuid)) {
                $subscription->uuid = (string) Str::ulid();
            }
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

    public function charges(): HasMany
    {
        return $this->hasMany(ExternalSubscriptionCharge::class);
    }

    public function isActive(): bool
    {
        return strtoupper((string) $this->status) === 'ACTIVE';
    }

    public function isDueForCharge(): bool
    {
        return $this->isActive()
            && $this->next_charge_at !== null
            && $this->next_charge_at->lte(now())
            && filled($this->stored_card_id);
    }

    public function scheduleNextCharge(): void
    {
        $days = max(1, (int) $this->interval_days);
        $this->next_charge_at = now()->addDays($days);
        $this->save();
    }
}
