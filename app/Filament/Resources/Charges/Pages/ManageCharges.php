<?php

namespace App\Filament\Resources\Charges\Pages;

use App\Filament\Resources\Charges\ChargeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageCharges extends ManageRecords
{
    protected static string $resource = ChargeResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        if (! request()->has('demo')) {
            return null;
        }

        return request()->boolean('demo')
            ? __('Showing demo charges')
            : __('Showing real charges');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
