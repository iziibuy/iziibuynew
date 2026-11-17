@php
    /** @var array{summary: string, period: string, lines: list<array{label: string, amount: float, note: ?string}>, total: float, currency: string} $breakdown */
    $currency = $breakdown['currency'] ?? 'NOK';
@endphp

<div class="space-y-4 text-sm">
    <p class="text-gray-600 dark:text-gray-300">{{ $breakdown['summary'] ?? '' }}</p>

    <dl class="divide-y divide-gray-200 rounded-lg bg-gray-50 px-3 dark:divide-white/10 dark:bg-white/5">
        <div class="flex items-center justify-between gap-4 py-2">
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Billing period') }}</dt>
            <dd class="text-gray-950 dark:text-white">{{ $breakdown['period'] ?? '-' }}</dd>
        </div>
    </dl>

    <table class="w-full">
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach (($breakdown['lines'] ?? []) as $line)
                <tr>
                    <td class="py-2 pe-4 align-top">
                        <div class="text-gray-950 dark:text-white">{{ $line['label'] }}</div>
                        @if (filled($line['note'] ?? null))
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $line['note'] }}</div>
                        @endif
                    </td>
                    <td class="py-2 text-end align-top tabular-nums text-gray-950 dark:text-white">
                        {{ number_format((float) $line['amount'], 2) }} {{ $currency }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="border-t-2 border-gray-300 dark:border-white/20">
            <tr>
                <td class="py-2 pe-4 font-semibold text-gray-950 dark:text-white">{{ __('Due bill') }}</td>
                <td class="py-2 text-end font-semibold tabular-nums text-gray-950 dark:text-white">
                    {{ number_format((float) ($breakdown['total'] ?? 0), 2) }} {{ $currency }}
                </td>
            </tr>
        </tfoot>
    </table>
</div>
