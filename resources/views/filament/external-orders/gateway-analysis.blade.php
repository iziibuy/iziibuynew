<div class="space-y-4 text-sm">
    @if (! empty($result['error']))
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-danger-700 dark:border-danger-600 dark:bg-danger-950 dark:text-danger-200">
            <strong>{{ __('Error') }}:</strong> {{ $result['error'] }}
        </div>
    @endif

    <div>
        <h3 class="mb-2 font-semibold">{{ __('Summary') }}</h3>
        <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($result['summary'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>

    <div>
        <h3 class="mb-2 font-semibold">{{ __('Local order') }}</h3>
        <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($result['local'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>

    <div>
        <h3 class="mb-2 font-semibold">{{ __('Gateway API response') }} ({{ $result['provider'] ?? 'unknown' }})</h3>
        <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($result['gateway'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
</div>
