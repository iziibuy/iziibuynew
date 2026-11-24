<?php

namespace App\Filament\Resources\PaymentMethodAccesses\RelationManagers;

use App\Models\PaymentApi;
use App\Models\PaymentMethodAccess;
use App\Payment\Elavon\CheckoutJsTheme;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;

class PaymentapisRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentapis';

    protected static ?string $title = 'Iziipay APIs';

    public function form(Schema $schema): Schema
    {
        $checkoutJsVisible = fn (Get $get): bool => $this->ownerIsElavon()
            && $get('elavon_link_mode') === PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS;

        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Iziipay API')
                    ->persistTabInQueryString(false)
                    ->contained(false)
                    ->tabs([
                        Tab::make(__('Connection'))
                            ->icon(Heroicon::OutlinedLink)
                            ->schema([
                                Section::make(__('API credentials'))
                                    ->description(__('The source key identifies this integration in the Iziipay SDK and API requests.'))
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('key')
                                            ->label(__('Source key'))
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(PaymentApi::class, 'key', ignoreRecord: true)
                                            ->copyable()
                                            ->columnSpanFull()
                                            ->helperText(__('Used as source_key in the Iziipay JavaScript SDK and API requests.')),
                                        TextInput::make('domain')
                                            ->label(__('Domain'))
                                            ->url()
                                            ->required()
                                            ->maxLength(255),
                                        Toggle::make('status')
                                            ->label(__('Active'))
                                            ->default(true)
                                            ->inline(false),
                                    ]),
                                Section::make(__('Redirects'))
                                    ->description(__('Where customers are sent after paying, failing or cancelling.'))
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('success_redirect_url')
                                            ->label(__('Success redirect URL'))
                                            ->url()
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('failed_redirect_url')
                                            ->label(__('Failed redirect URL'))
                                            ->url()
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('cancel_callback_url')
                                            ->label(__('Cancel callback URL'))
                                            ->url()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ]),
                                Section::make(__('Elavon payment link'))
                                    ->visible(fn (): bool => $this->ownerIsElavon())
                                    ->schema([
                                        Select::make('elavon_link_mode')
                                            ->label(__('Payment page type'))
                                            ->options([
                                                PaymentApi::ELAVON_LINK_MODE_HOSTED => __('Hosted payment page (Elavon HPP redirect)'),
                                                PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS => __('Own CheckoutJS page (card fields on our payment page)'),
                                            ])
                                            ->default(PaymentApi::ELAVON_LINK_MODE_HOSTED)
                                            ->native(false)
                                            ->live()
                                            ->helperText(__('Hosted sends customers to Elavon. CheckoutJS shows your branded payment page using Elavon Hosted Fields. Branding options appear in the next tabs.')),
                                    ]),
                            ]),
                        Tab::make(__('Branding'))
                            ->icon(Heroicon::OutlinedPaintBrush)
                            ->visible($checkoutJsVisible)
                            ->schema([
                                Section::make(__('Identity'))
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('checkoutjs_appearance.company_name')
                                            ->label(__('Company name'))
                                            ->maxLength(120),
                                        FileUpload::make('checkoutjs_appearance.logo')
                                            ->label(__('Logo'))
                                            ->image()
                                            ->disk(CheckoutJsTheme::disk())
                                            ->directory('checkoutjs-logos')
                                            ->maxSize(2048)
                                            ->helperText(__('PNG, JPG, or WebP. Up to 2 MB.')),
                                    ]),
                                Section::make(__('Colors'))
                                    ->columns(3)
                                    ->schema([
                                        ColorPicker::make('checkoutjs_appearance.primary_color')
                                            ->label(__('Primary color'))
                                            ->default(CheckoutJsTheme::DEFAULT_PRIMARY),
                                        ColorPicker::make('checkoutjs_appearance.secondary_color')
                                            ->label(__('Accent color'))
                                            ->default(CheckoutJsTheme::DEFAULT_SECONDARY),
                                        ColorPicker::make('checkoutjs_appearance.background_color')
                                            ->label(__('Background'))
                                            ->default(CheckoutJsTheme::DEFAULT_BACKGROUND),
                                    ]),
                            ]),
                        Tab::make(__('Texts'))
                            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                            ->visible($checkoutJsVisible)
                            ->schema([
                                Section::make(__('Form'))
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('checkoutjs_appearance.heading')
                                            ->label(__('Form heading'))
                                            ->maxLength(120),
                                        TextInput::make('checkoutjs_appearance.summary_note')
                                            ->label(__('Summary note'))
                                            ->maxLength(200),
                                        TextInput::make('checkoutjs_appearance.pay_button_label')
                                            ->label(__('Pay button label'))
                                            ->maxLength(80),
                                        TextInput::make('checkoutjs_appearance.cancel_label')
                                            ->label(__('Cancel link text'))
                                            ->maxLength(80),
                                        Textarea::make('checkoutjs_appearance.lead')
                                            ->label(__('Form description'))
                                            ->rows(2)
                                            ->maxLength(400)
                                            ->columnSpanFull(),
                                        Textarea::make('checkoutjs_appearance.footer')
                                            ->label(__('Footer'))
                                            ->rows(2)
                                            ->maxLength(300)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make(__('Advanced'))
                            ->icon(Heroicon::OutlinedCodeBracket)
                            ->visible($checkoutJsVisible)
                            ->schema([
                                Section::make(__('Custom CSS'))
                                    ->schema([
                                        Textarea::make('checkoutjs_appearance.custom_css')
                                            ->hiddenLabel()
                                            ->rows(12)
                                            ->extraInputAttributes(['class' => 'font-mono text-sm'])
                                            ->maxLength(CheckoutJsTheme::CUSTOM_CSS_MAX_LENGTH)
                                            ->helperText(__('Optional styles for the checkout page. HTML and JavaScript are not allowed.')),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->columns([
                TextColumn::make('key')
                    ->label(__('Source key'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('domain')
                    ->searchable(),
                TextColumn::make('success_redirect_url')
                    ->label(__('Success URL'))
                    ->toggleable(),
                TextColumn::make('failed_redirect_url')
                    ->label(__('Failed URL'))
                    ->toggleable(),
                TextColumn::make('cancel_callback_url')
                    ->label(__('Cancel callback'))
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('status')
                    ->boolean(),
                TextColumn::make('elavon_link_mode')
                    ->label(__('Elavon link'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS => __('CheckoutJS'),
                        default => __('Hosted'),
                    })
                    ->color(fn (?string $state): string => $state === PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS ? 'info' : 'gray')
                    ->visible(fn (): bool => $this->ownerIsElavon()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth(Width::FourExtraLarge)
                    ->label(__('Create Iziipay API'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['key'] = filled($data['key'] ?? null)
                            ? $data['key']
                            : PaymentApi::generateKey();
                        $data['status'] = $data['status'] ?? true;

                        return $this->sanitizeCheckoutAppearanceData($data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::FourExtraLarge)
                    ->mutateFormDataUsing(function (array $data): array {
                        $record = $this->getMountedTableActionRecord();

                        return $this->sanitizeCheckoutAppearanceData(
                            $data,
                            $record instanceof PaymentApi ? $record : null,
                        );
                    }),
                Action::make('regenerateKey')
                    ->label(__('Regenerate key'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('Regenerate source key?'))
                    ->modalDescription(__('Integrations using the current source_key must be updated after regeneration.'))
                    ->action(function (PaymentApi $record): void {
                        $record->update(['key' => PaymentApi::generateKey()]);

                        Notification::make()
                            ->title(__('Source key regenerated'))
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function ownerIsElavon(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof PaymentMethodAccess
            && $owner->paymentMethod === 'elavon';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeCheckoutAppearanceData(array $data, ?PaymentApi $record = null): array
    {
        if (($data['elavon_link_mode'] ?? PaymentApi::ELAVON_LINK_MODE_HOSTED) !== PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS) {
            return $data;
        }

        $raw = is_array($data['checkoutjs_appearance'] ?? null) ? $data['checkoutjs_appearance'] : [];
        $theme = CheckoutJsTheme::fromApi($record);
        $logo = $raw['logo'] ?? null;
        $logoFile = $logo instanceof UploadedFile ? $logo : null;
        $removeLogo = array_key_exists('logo', $raw) && blank($logo) && ! $logoFile;

        $appearance = $theme->persist([
            'checkout_company_name' => $raw['company_name'] ?? '',
            'checkout_heading' => $raw['heading'] ?? '',
            'checkout_lead' => $raw['lead'] ?? '',
            'checkout_pay_button_label' => $raw['pay_button_label'] ?? '',
            'checkout_cancel_label' => $raw['cancel_label'] ?? '',
            'checkout_summary_note' => $raw['summary_note'] ?? '',
            'checkout_primary_color' => $this->normalizeColor($raw['primary_color'] ?? null),
            'checkout_secondary_color' => $this->normalizeColor($raw['secondary_color'] ?? null),
            'checkout_background_color' => $this->normalizeColor($raw['background_color'] ?? null),
            'checkout_footer' => $raw['footer'] ?? '',
            'checkout_custom_css' => $raw['custom_css'] ?? '',
        ], $logoFile, $removeLogo);

        if (is_string($logo) && $logo !== '') {
            $appearance['logo'] = $logo;
        }

        $data['checkoutjs_appearance'] = $appearance;

        return $data;
    }

    protected function normalizeColor(mixed $color): ?string
    {
        if (! is_string($color) || trim($color) === '') {
            return null;
        }

        $color = strtoupper(trim($color));

        if (! str_starts_with($color, '#')) {
            $color = '#'.$color;
        }

        return $color;
    }
}
