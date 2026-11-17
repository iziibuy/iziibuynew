@php
    /** @var array{user_id: int, user: ?array{id: int, name: string, email: string}, billing_period: string, entities: list<array<string, mixed>>} $inspection */
@endphp

<div class="space-y-4 text-sm">
    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <p class="font-medium text-gray-950 dark:text-white">
            {{ __('Owner #:id', ['id' => $inspection['user_id']]) }}
        </p>
        @if ($inspection['user'] === null)
            <p class="mt-1 text-danger-600 dark:text-danger-400">
                {{ __('No user exists with this ID.') }}
            </p>
        @else
            <p class="mt-1 text-gray-600 dark:text-gray-300">
                {{ $inspection['user']['name'] }}
                @if (filled($inspection['user']['email']))
                    · {{ $inspection['user']['email'] }}
                @endif
            </p>
        @endif
        <p class="mt-2 text-gray-500 dark:text-gray-400">
            {{ __('Billing period') }}: {{ $inspection['billing_period'] }}
        </p>
    </div>

    @foreach ($inspection['entities'] as $entity)
        <div @class([
            'rounded-lg border p-4',
            'border-success-300 dark:border-success-700' => ($entity['on_due_list'] ?? false) === true,
            'border-warning-300 dark:border-warning-700' => ($entity['on_due_list'] ?? false) === false && ($entity['status'] ?? '') !== 'missing',
            'border-gray-200 dark:border-gray-700' => ($entity['status'] ?? '') === 'missing',
        ])>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="font-medium text-gray-950 dark:text-white">
                        {{ $entity['type_label'] ?? __('Account') }}
                        @if (filled($entity['company'] ?? null))
                            — {{ $entity['company'] }}
                        @endif
                        @if (filled($entity['entity_id'] ?? null))
                            <span class="text-gray-500 dark:text-gray-400">#{{ $entity['entity_id'] }}</span>
                        @endif
                    </p>
                </div>
                <span @class([
                    'inline-flex rounded-md px-2 py-1 text-xs font-medium',
                    'bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-200' => ($entity['on_due_list'] ?? false) === true,
                    'bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-200' => ($entity['on_due_list'] ?? false) === false && ($entity['status'] ?? '') !== 'missing',
                    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' => ($entity['status'] ?? '') === 'missing',
                ])>
                    {{ $entity['status_label'] ?? '' }}
                </span>
            </div>

            @if (($entity['due_amount'] ?? null) !== null)
                <p class="mt-2 text-gray-700 dark:text-gray-200">
                    {{ __('Due bill') }}:
                    <strong>{{ number_format((float) $entity['due_amount'], 2) }} NOK</strong>
                </p>
            @endif

            @if (filled($entity['last_paid_at'] ?? null))
                <p class="mt-1 text-gray-500 dark:text-gray-400">
                    {{ __('Last paid') }}: {{ \Illuminate\Support\Carbon::parse($entity['last_paid_at'])->format('Y-m-d H:i') }}
                </p>
            @endif

            <ul class="mt-3 list-disc space-y-1 pl-5 text-gray-700 dark:text-gray-200">
                @foreach (($entity['reasons'] ?? []) as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
