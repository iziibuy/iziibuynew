@php
    /** @var array{method: string, summary: string, period: ?string, lines: list<array{label: string, amount: float, note: ?string}>, total: float, charged: float, currency: string, notes: list<string>} $breakdown */
    /** @var string|null $cardLastFour */
    $currency = $breakdown['currency'];
    $methodLabel = match ($breakdown['method']) {
        \App\Services\Admin\ChargeBreakdown::METHOD_SNAPSHOT => __('Saved at charge time'),
        \App\Services\Admin\ChargeBreakdown::METHOD_RECONSTRUCTED => __('Recalculated'),
        default => __('As charged'),
    };
@endphp

<div class="space-y-4 text-sm">
    <div class="flex items-start justify-between gap-4">
        <p class="text-gray-600 dark:text-gray-300">{{ $breakdown['summary'] }}</p>
        <span class="shrink-0 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
            {{ $methodLabel }}
        </span>
    </div>

    <dl class="divide-y divide-gray-200 rounded-lg bg-gray-50 px-3 dark:divide-white/10 dark:bg-white/5">
        @if (filled($breakdown['period']))
            <div class="flex items-center justify-between gap-4 py-2">
                <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Billing period') }}</dt>
                <dd class="text-gray-950 dark:text-white">{{ $breakdown['period'] }}</dd>
            </div>
        @endif
        <div class="flex items-center justify-between gap-4 py-2">
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Card') }}</dt>
            <dd class="tabular-nums text-gray-950 dark:text-white">
                @if (filled($cardLastFour ?? null))
                    •••• {{ $cardLastFour }}
                @else
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Not available') }}</span>
                @endif
            </dd>
        </div>
    </dl>

    <table class="w-full">
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($breakdown['lines'] as $line)
                <tr>
                    <td class="py-2 pe-4 align-top">
                        <div class="text-gray-950 dark:text-white">{{ $line['label'] }}</div>
                        @if (filled($line['note']))
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $line['note'] }}</div>
                        @endif
                    </td>
                    <td class="py-2 text-end align-top tabular-nums text-gray-950 dark:text-white">
                        {{ number_format($line['amount'], 2) }} {{ $currency }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="border-t-2 border-gray-300 dark:border-white/20">
            <tr>
                <td class="py-2 pe-4 font-semibold text-gray-950 dark:text-white">{{ __('Total charged') }}</td>
                <td class="py-2 text-end font-semibold tabular-nums text-gray-950 dark:text-white">
                    {{ number_format($breakdown['charged'], 2) }} {{ $currency }}
                </td>
            </tr>
        </tfoot>
    </table>

    @foreach ($breakdown['notes'] as $note)
        <p class="rounded-lg bg-warning-50 p-3 text-xs text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">{{ $note }}</p>
    @endforeach
</div>
