@php
    /** @var \App\Models\RetailerMeta $record */
    $record = $getRecord();
    $url = route('shop.register', ['refferal' => $record->user_id]);
@endphp

<div class="flex justify-center py-1" title="{{ $url }}">
    {!! QrCode::size(72)->generate($url) !!}
</div>
