<?php

namespace Database\Factories;

use App\Models\ChargeRefund;
use App\Models\SubscriptionCharge;
use App\Services\Admin\UnifiedChargeLedger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargeRefund>
 */
class ChargeRefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $refundableId = fake()->unique()->numberBetween(1, 1_000_000);

        return [
            'ledger_key' => UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$refundableId,
            'refundable_type' => SubscriptionCharge::class,
            'refundable_id' => $refundableId,
            'amount' => fake()->randomFloat(2, 50, 500),
            'currency' => 'NOK',
            'provider' => 'elavon',
            'provider_operation' => ChargeRefund::OPERATION_REFUND,
            'provider_transaction_id' => fake()->regexify('[a-z0-9]{28}'),
            'original_transaction_id' => fake()->regexify('[a-z0-9]{28}'),
            'reason' => fake()->sentence(),
            'rollback' => [],
        ];
    }

    /**
     * Point the refund at a specific ledger row.
     */
    public function forLedgerKey(string $ledgerKey, string $refundableType): static
    {
        [, $id] = UnifiedChargeLedger::parseKey($ledgerKey);

        return $this->state(fn (): array => [
            'ledger_key' => $ledgerKey,
            'refundable_type' => $refundableType,
            'refundable_id' => $id,
        ]);
    }
}
