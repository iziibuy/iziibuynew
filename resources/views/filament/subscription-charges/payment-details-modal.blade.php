@if ($details === null || $details === [])
    <p class="text-sm text-gray-600">Nothing found</p>
@else
    @include('filament.subscription-charges.partials.nested-list', ['items' => $details])
@endif
