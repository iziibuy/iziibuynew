<?php

namespace App\Filament\Resources\Shops\Pages;

use App\Filament\Resources\Shops\ShopResource;
use App\Models\Shop;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class AdvanceEditShop extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ShopResource::class;

    protected static ?string $navigationLabel = 'Advance edit';

    protected static ?string $title = 'Advance edit';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.resources.shops.pages.advance-edit-shop';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->getRecord()->load('user');

        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        /** @var Shop $shop */
        $shop = $this->getRecord();

        return __('Advance edit').': '.$shop->user_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewShop')
                ->label(__('View shop'))
                ->icon(Heroicon::OutlinedEye)
                ->url(fn (): string => ShopResource::getUrl('view', ['record' => $this->getRecord()])),
            Action::make('backToList')
                ->label(__('Back to shops'))
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(fn (): string => ShopResource::getUrl()),
        ];
    }
}
