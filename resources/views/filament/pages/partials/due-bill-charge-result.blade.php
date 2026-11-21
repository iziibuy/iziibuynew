@php
    $entries = collect($result['entries'] ?? []);
    $counts = $entries->countBy('status');
    $styles = [
        'charged' => ['label' => __('Charged'), 'class' => 'border-success-300 bg-success-50 text-success-700'],
        'failed' => ['label' => __('Failed'), 'class' => 'border-danger-300 bg-danger-50 text-danger-700'],
        'skipped' => ['label' => __('Skipped'), 'class' => 'border-warning-300 bg-warning-50 text-warning-700'],
        'pending' => ['label' => __('Not processed'), 'class' => 'border-gray-300 bg-gray-50 text-gray-700'],
    ];
@endphp

<div class="space-y-4 text-sm">
    <div class="flex flex-wrap gap-2">
        @foreach (['charged', 'failed', 'skipped', 'pending'] as $status)
            @if (($counts[$status] ?? 0) > 0)
                <span class="rounded-full border px-3 py-1 font-medium {{ $styles[$status]['class'] }}">
                    {{ $styles[$status]['label'] }}: {{ $counts[$status] }}
                </span>
            @endif
        @endforeach
    </div>

    @forelse ($entries as $entry)
        @php($style = $styles[$entry['status']] ?? $styles['pending'])
        <div class="rounded-lg border p-3 {{ $style['class'] }}">
            <div class="flex items-center justify-between gap-2">
                <span class="font-semibold">{{ $entry['account'] }}</span>
                <span class="text-xs font-bold uppercase">{{ $style['label'] }}</span>
            </div>
            <ul class="mt-2 space-y-1 break-words font-mono text-xs">
                @foreach ($entry['details'] as $detail)
                    <li>{{ $detail }}</li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="text-gray-500">{{ __('The charge command returned no accounts and no output.') }}</p>
    @endforelse
</div>
