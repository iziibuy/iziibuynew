<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        /** @var User $user */
        $user = $this->getRecord();

        return [
            Action::make('loginAsUser')
                ->label(__('Login as user'))
                ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                ->color('warning')
                ->url(route('admin.users.login-as', $user))
                ->visible(fn (): bool => auth()->user()?->id !== $user->id
                    && auth()->user() instanceof User
                    && auth()->user()->isAdmin()),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
