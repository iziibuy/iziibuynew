<?php

namespace App\Filament\Resources\Shops\Pages;

use App\Filament\Resources\Shops\ShopResource;
use App\Models\Shop;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditShop extends EditRecord
{
    protected static string $resource = ShopResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        if (! array_key_exists('terms', $data)) {
            return $data;
        }

        $terms = $data['terms'];

        if (is_array($terms)) {
            $locale = app()->getLocale();
            $fallbackLocale = (string) config('app.fallback_locale');

            $terms = $terms[$locale]
                ?? $terms[$fallbackLocale]
                ?? collect($terms)->first(fn (mixed $value): bool => filled($value));
        }

        if (! is_string($terms) || blank($terms)) {
            /** @var Shop $shop */
            $shop = $this->getRecord();
            $terms = $shop->terms;
        }

        $data['terms'] = filled($terms) && is_string($terms) ? $terms : null;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        /** @var Shop $shop */
        $shop = $this->getRecord();

        return [
            Action::make('sendPassword')
                ->label('Generate password and sent')
                ->icon(Heroicon::OutlinedKey)
                ->url(route('admin.send.shop.password', $shop)),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
