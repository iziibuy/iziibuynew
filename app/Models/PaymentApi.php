<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentApi extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
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
