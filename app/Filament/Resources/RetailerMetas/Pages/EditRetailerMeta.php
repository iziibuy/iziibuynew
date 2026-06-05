<?php

namespace App\Filament\Resources\RetailerMetas\Pages;

use App\Filament\Resources\RetailerMetas\RetailerMetaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRetailerMeta extends EditRecord
{
    protected static string $resource = RetailerMetaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->using(function (Model $record): bool {
                    $user = $record->user;
                    $record->delete();
                    $user?->delete();

                    return true;
                }),
        ];
    }
}
