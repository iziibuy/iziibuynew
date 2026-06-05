@php
    /** @var \App\Models\Shop $record */
    $record = $getRecord();
    $url = route('shop.home', ['user_name' => $record->user_name]);
@endphp

<div class="flex justify-center py-1">
    {!! QrCode::size(72)->generate($url) !!}
</div>
