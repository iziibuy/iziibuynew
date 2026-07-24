<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\CheckoutPaymentOption;
use Illuminate\Support\Facades\Schema;

class CheckoutPaymentOptionCatalog
{
    /**
     * Merged catalog: config defaults, overridden by DB rows with the same key.
     *
     * @return array<string, array{label: string, icon: string|null, acquirers: list<string>, sort: int, active: bool}>
     */
    public function all(): array
    {
        $options = [];

        foreach (config('checkout_payment_options.options', []) as $key => $option) {
            if (! is_string($key) || $key === '' || ! is_array($option)) {
                continue;
            }

            $options[$key] = $this->normalizeOption($key, $option);
        }

        if (Schema::hasTable('checkout_payment_options')) {
            foreach (CheckoutPaymentOption::query()->orderBy('sort')->get() as $row) {
                $options[$row->key] = $this->normalizeOption($row->key, [
                    'label' => $row->label,
                    'icon' => $row->icon,
                    'acquirers' => $row->acquirers ?? [],
                    'sort' => $row->sort,
                    'active' => $row->is_active,
                ]);
            }
        }

        uasort($options, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $options;
    }

    /**
     * @return array<string, array{label: string, icon: string|null, acquirers: list<string>, sort: int, active: bool}>
     */
    public function active(): array
    {
        return array_filter(
            $this->all(),
            fn (array $option): bool => $option['active'] === true
        );
    }

    /**
     * @return array<string, string>
     */
    public function acquirerLabels(): array
    {
        /** @var array<string, string> $labels */
        $labels = config('checkout_payment_options.acquirers', []);

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array{label: string, icon: string|null, acquirers: list<string>, sort: int, active: bool}
     */
    protected function normalizeOption(string $key, array $option): array
    {
        $acquirers = array_values(array_filter(
            array_map('strval', (array) ($option['acquirers'] ?? [])),
            fn (string $acquirer): bool => $acquirer !== ''
        ));

        return [
            'label' => (string) ($option['label'] ?? ucfirst($key)),
            'icon' => isset($option['icon']) && is_string($option['icon']) && $option['icon'] !== ''
                ? $option['icon']
                : $key,
            'acquirers' => $acquirers,
            'sort' => (int) ($option['sort'] ?? 0),
            'active' => (bool) ($option['active'] ?? $option['is_active'] ?? true),
        ];
    }
}
