@php
    $inspection = $this->ownerInspection;
@endphp

@if (is_array($inspection))
    <div class="mb-6">
        @include('filament.pages.partials.due-bill-owner-inspection', ['inspection' => $inspection])
    </div>
@endif
