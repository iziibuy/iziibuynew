<?php

namespace App\Filament\Resources\EnterpriseOnboardings;

use App\Filament\Resources\EnterpriseOnboardings\Pages\ManageEnterpriseOnboardings;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\EnterpriseOnboarding;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnterpriseOnboardingResource extends Resource
{
    protected static ?string $model = EnterpriseOnboarding::class;

    protected static string|\UnitEnum|null $navigationGroup = 'onboarding';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Enterprise onboarding';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'company_name';

    protected static ?int $globalSearchSort = 15;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['company_name', 'company_email', 'key'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('key'),
                TextInput::make('company_name')
                    ->required(),
                TextInput::make('company_email')
                    ->email()
                    ->required(),
                Textarea::make('company_address')
                    ->required()
                    ->rows(4)
                    ->helperText(__('JSON object with street, zip, city, etc., or a single-line address'))
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state === null || $state === '') {
                            return '';
                        }
                        if (is_string($state)) {
                            return $state;
                        }
                        if (is_object($state) || is_array($state)) {
                            try {
                                $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                                return is_string($encoded) ? $encoded : '';
                            } catch (\JsonException) {
                                return '';
                            }
                        }

                        return '';
                    })
                    ->dehydrateStateUsing(function (mixed $state): array|string|null {
                        if ($state === null) {
                            return null;
                        }
                        if (! is_string($state)) {
                            return null;
                        }
                        $trimmed = trim($state);
                        if ($trimmed === '') {
                            return null;
                        }
                        try {
                            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                            if (is_array($decoded)) {
                                return $decoded;
                            }
                        } catch (\JsonException) {
                            // Plain text / legacy single-line address
                        }

                        return $trimmed;
                    }),
                TextInput::make('company_registration')
                    ->required(),
                TextInput::make('company_domain')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('fee')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_establishment')
                    ->required(),
                TextInput::make('establishment_fee')
                    ->required()
                    ->numeric()
                    ->default(0),
                DatePicker::make('last_paid_at'),
                TextInput::make('paymentMethod')
                    ->required()
                    ->default('quickpay'),
                TextInput::make('api'),
                TextInput::make('contract_url')
                    ->url(),
                TextInput::make('contract_signed')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('contract_status')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('kyc_status')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('key')
                    ->searchable(),
                TextColumn::make('company_name')
                    ->searchable(),
                TextColumn::make('company_email')
                    ->searchable(),
                TextColumn::make('company_address')
                    ->searchable()
                    ->formatStateUsing(fn (mixed $state): string => EnterpriseOnboarding::companyAddressAsString($state)),
                TextColumn::make('company_registration')
                    ->searchable(),
                TextColumn::make('company_domain')
                    ->searchable(),
                TextColumn::make('status')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('fee')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_establishment')
                    ->boolean(),
                TextColumn::make('establishment_fee')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_paid_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('paymentMethod')
                    ->searchable(),
                TextColumn::make('api')
                    ->searchable(),
                TextColumn::make('contract_url')
                    ->searchable(),
                TextColumn::make('contract_signed')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('contract_status')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('kyc_status')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                ResourceTableFilters::boolean('is_establishment', __('Establishment')),
                ResourceTableFilters::boolean('kyc_status', __('KYC approved')),
                ResourceTableFilters::select('paymentMethod', [
                    'quickpay' => 'QuickPay',
                    'elavon' => 'Elavon',
                    'surfboard' => 'Surfboard',
                ], __('Payment method')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEnterpriseOnboardings::route('/'),
        ];
    }
}
