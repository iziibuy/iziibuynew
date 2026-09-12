@php
    /** @var array<string, mixed> $record */
    $extras = is_array($record['extras'] ?? null) ? $record['extras'] : [];
@endphp

<div class="space-y-6 text-sm">
    <dl class="grid gap-4 sm:grid-cols-2">
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Source') }}</dt>
            <dd class="mt-1 text-gray-950 dark:text-white">{{ $record['source_label'] ?? '-' }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Model') }}</dt>
            <dd class="mt-1 text-gray-950 dark:text-white">
                {{ \App\Services\Admin\UnifiedChargeLedger::modelOptions()[$record['model_type'] ?? ''] ?? ($record['model_type'] ?? '-') }}
                @if (filled($record['model_id'] ?? null))
                    <span class="text-gray-500">#{{ $record['model_id'] }}</span>
                @endif
            </dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Account / entity') }}</dt>
            <dd class="mt-1 text-gray-950 dark:text-white">{{ $record['model_label'] ?? '-' }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Reference') }}</dt>
            <dd class="mt-1 text-gray-950 dark:text-white">{{ $record['reference'] ?? '-' }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Amount') }}</dt>
            <dd class="mt-1 font-semibold text-gray-950 dark:text-white">
                {{ number_format((float) ($record['amount'] ?? 0), 2) }} {{ $record['currency'] ?? 'NOK' }}
            </dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</dt>
            <dd class="mt-1 text-gray-950 dark:text-white">{{ $record['status_label'] ?? '-' }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Date') }}</dt>
            <dd class="mt-1 text-gray-950 dark:text-white">{{ $record['created_at'] ?? '-' }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Email') }}</dt>
            <dd class="mt-1 text-gray-950 dark:text-white">{{ $record['email'] ?: '-' }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Description') }}</dt>
            <dd class="mt-1 text-gray-950 dark:text-white">{{ $record['description'] ?: '-' }}</dd>
        </div>
    </dl>

    @if ($extras !== [])
        <div>
            <h4 class="mb-2 font-medium text-gray-950 dark:text-white">{{ __('Extra details') }}</h4>
            <dl class="grid gap-3 sm:grid-cols-2">
                @foreach ($extras as $key => $value)
                    @if (in_array($key, ['details', 'payment_body', 'payment_details', 'charge_details', 'view_url'], true))
                        @continue
                    @endif
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', (string) $key) }}</dt>
                        <dd class="mt-1 break-words text-gray-950 dark:text-white">
                            @if (is_bool($value))
                                {{ $value ? __('Yes') : __('No') }}
                            @elseif (is_scalar($value) || $value === null)
                                {{ filled($value) ? $value : '-' }}
                            @else
                                {{ json_encode($value) }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    @foreach (['details' => __('Details'), 'payment_body' => __('Payment body'), 'payment_details' => __('Payment details'), 'charge_details' => __('Charge details')] as $key => $label)
        @if (filled($extras[$key] ?? null))
            <div>
                <h4 class="mb-2 font-medium text-gray-950 dark:text-white">{{ $label }}</h4>
                <pre class="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-100">{{ $extras[$key] }}</pre>
            </div>
        @endif
    @endforeach
</div>
