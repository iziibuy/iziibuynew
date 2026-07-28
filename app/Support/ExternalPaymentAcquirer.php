<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ExternalBooking;
use App\Models\ExternalOrder;

/**
 * Resolves the single acquirer used for a payment when plugins store
 * comma-separated values like "elavon,surfboard".
 */
final class ExternalPaymentAcquirer
{
    /**
     * @return 'elavon'|'surfboard'|'unknown'
     */
    public static function resolve(
        ?string $storedMethod,
        ?string $elavonTransactionId = null,
        bool $hasSurfboardEvidence = false,
    ): string {
        if ($hasSurfboardEvidence) {
            return 'surfboard';
        }

        if (filled($elavonTransactionId)) {
            return 'elavon';
        }

        $methods = collect(explode(',', (string) $storedMethod))
            ->map(fn (string $method): string => strtolower(trim($method)))
            ->filter()
            ->values();

        if ($methods->count() === 1) {
            $only = (string) $methods->first();

            return in_array($only, ['elavon', 'surfboard'], true) ? $only : 'unknown';
        }

        // Dual-enabled plugins historically fell through to Elavon (strict == 'surfboard' failed).
        if ($methods->contains('elavon')) {
            return 'elavon';
        }

        if ($methods->contains('surfboard')) {
            return 'surfboard';
        }

        return 'unknown';
    }

    /**
     * @return 'elavon'|'surfboard'|'unknown'
     */
    public static function forBooking(ExternalBooking $booking): string
    {
        // Read meta from DB/relation — avoid HasMeta attribute cache, which can leak across
        // RefreshDatabase tests when record IDs reset (e.g. always id=1).
        $elavonId = $booking->metas()
            ->where('column_name', 'elavon_transaction_id')
            ->value('column_value');

        return self::resolve(
            $booking->getAttributes()['payment_method'] ?? $booking->payment_method,
            filled($elavonId) ? (string) $elavonId : null,
        );
    }

    /**
     * @return 'elavon'|'surfboard'|'unknown'
     */
    public static function forOrder(ExternalOrder $order): string
    {
        $hasSurfboardEvidence = filled($order->surfboard_transaction_id ?? null);

        $elavonId = null;
        if (filled($order->elavon_transaction_id ?? null)) {
            $elavonId = (string) $order->elavon_transaction_id;
        } elseif (is_string($order->response) && filled($order->response)) {
            $elavonId = $order->response;
        }

        return self::resolve($order->payment_method, $elavonId, $hasSurfboardEvidence);
    }
}
