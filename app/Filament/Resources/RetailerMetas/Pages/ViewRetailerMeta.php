<?php

namespace App\Filament\Resources\RetailerMetas\Pages;

use App\Filament\Pages\RetailerReportPage;
use App\Filament\Pages\RetailerWithdrawalsPage;
use App\Filament\Resources\RetailerMetas\RetailerMetaResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\RetailerMeta;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ViewRetailerMeta extends ViewRecord
{
    protected static string $resource = RetailerMetaResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        /** @var RetailerMeta $record */
        $record = $this->getRecord();

        return [
            Action::make('withdrawals')
                ->label(__('Withdrawals'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->url(RetailerWithdrawalsPage::getUrl().'?user='.$record->user_id),
            Action::make('withdrawBalance')
                ->label(__('Withdraw Balance'))
                ->icon(Heroicon::OutlinedArrowDownCircle)
                ->modalHeading(__('Withdraw Balance'))
                ->modalSubmitActionLabel(__('Withdraw'))
                ->visible(fn (): bool => $record->user_id !== null)
                ->schema([
                    TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(max(0, (float) ($record->user?->totalBalance() ?? 0)))
                        ->helperText(__('Available balance: :balance NOK', ['balance' => $record->user?->totalBalance() ?? 0])),
                    TextInput::make('trnx_id')
                        ->label(__('Trnx ID'))
                        ->maxLength(50),
                    DatePicker::make('date')
                        ->required()
                        ->default(now()),
                ])
                ->action(fn (array $data): mixed => RetailerMetaResource::placeWithdrawal($record->user, $data)),
            Action::make('report')
                ->label(__('Report'))
                ->icon(Heroicon::OutlinedChartBar)
                ->url(RetailerReportPage::getUrl(['user' => $record->user_id])),
            EditAction::make(),
            ActionGroup::make([
                Action::make('editProfile')
                    ->label(__('Edit Profile'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): string => UserResource::getUrl('edit', ['record' => $record->user_id]))
                    ->visible(fn (): bool => $record->user_id !== null),
                DeleteAction::make()
                    ->using(function (Model $model): bool {
                        if (! $model instanceof RetailerMeta) {
                            return false;
                        }

                        $user = $model->user;
                        $model->delete();
                        $user?->delete();

                        return true;
                    }),
            ])
                ->label(__('More actions'))
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }
}
