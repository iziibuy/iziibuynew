<ul class="list-disc space-y-1 ps-5 text-sm text-gray-800">
    @foreach ($items as $key => $value)
        <li>
            @if (is_array($value))
                <p class="mb-1 font-medium">{{ \Illuminate\Support\Str::headline((string) $key) }}:</p>
                @include('filament.subscription-charges.partials.nested-list', ['items' => $value])
            @else
                <span class="font-medium">{{ \Illuminate\Support\Str::headline((string) $key) }}:</span>
                {{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}
            @endif
        </li>
    @endforeach
</ul>
