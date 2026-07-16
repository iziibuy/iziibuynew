<?php

namespace App\Filament\Resources\SubscriptionCharges\Tables;

use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\SubscriptionCharge;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionChargesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->extraAttributes([
                'class' => 'fi-voyager-subscription-charges-table',
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->enterpriseOnly())
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100])
            ->striped()
            ->columns([
                TextColumn::make('quickpay_order_id')
                    ->label('Quickpay Order Id')
                    ->getStateUsing(fn (SubscriptionCharge $record): string => (string) (
                        $record->quickpay_order_id
                        ?: $record->elavon_transaction_id
                        ?: '-'
                    ))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query->where('quickpay_order_id', 'like', '%'.$search.'%')
                                ->orWhere('elavon_transaction_id', 'like', '%'.$search.'%');
                        });
                    })
                    ->sortable(),
                TextColumn::make('subscription_id')
                    ->label('Subscription Id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state): string => (string) (int) $state)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('domain')
                    ->label('Domain')
                    ->state(fn (SubscriptionCharge $record): string => $record->domain)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('payment_details', 'like', '%'.$search.'%');
                    }),
                TextColumn::make('last4')
                    ->label('Last Four')
                    ->state(fn (SubscriptionCharge $record): string => $record->last4),
            ])
            ->filters([
                ResourceTableFilters::boolean('status', __('Paid')),
            ])
            ->recordActions([
                Action::make('paymentDetails')
                    ->label('See Payment Details')
                    ->color('primary')
                    ->visible(fn (SubscriptionCharge $record): bool => filled($record->getAttributes()['payment_details'] ?? null))
                    ->modalHeading('Charge Information')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (SubscriptionCharge $record): View => view('filament.subscription-charges.payment-details-modal', [
                        'details' => self::decodePaymentDetails($record->payment_details),
                    ])),
                ViewAction::make()
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('warning'),
                EditAction::make()
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray'),
                DeleteAction::make()
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decodePaymentDetails(?string $paymentDetails): ?array
    {
        if ($paymentDetails === null || trim($paymentDetails) === '') {
            return null;
        }

        $decoded = json_decode($paymentDetails, true);

        return is_array($decoded) ? $decoded : null;
    }
}
