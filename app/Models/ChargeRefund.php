<?php

namespace App\Models;

use Database\Factories\ChargeRefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A full refund of a platform charge, with the account changes it rolled back.
 */
class ChargeRefund extends Model
{
    /** @use HasFactory<ChargeRefundFactory> */
    use HasFactory;

    public const OPERATION_VOID = 'void';

    public const OPERATION_REFUND = 'refund';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rollback' => 'array',
        ];
    }

    public function refundable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
