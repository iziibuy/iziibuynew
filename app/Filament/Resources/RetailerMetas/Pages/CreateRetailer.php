<?php

namespace App\Filament\Resources\RetailerMetas\Pages;

use App\Filament\Resources\RetailerMetas\RetailerMetaResource;
use App\Mail\NotificationEmail;
use App\Models\RetailerMeta;
use App\Models\RetailerType;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class CreateRetailer extends CreateRecord
{
    protected static string $resource = RetailerMetaResource::class;

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        $parentOptions = fn (): array => RetailerMeta::query()
            ->whereNull('parent_id')
            ->where('type', 4)
            ->with('user')
            ->get()
            ->mapWithKeys(fn (RetailerMeta $m): array => [
                (string) $m->user_id => ($m->user->email ?? '').' ('.trim(($m->user->name ?? '').' '.($m->user->last_name ?? '')).')',
            ])
            ->all();

        return $schema
            ->columns(2)
            ->components([
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique('users', 'email'),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(4),
                Select::make('type')
                    ->label('Retailer type')
                    ->options(fn (): array => RetailerType::query()->pluck('label', 'id')->all())
                    ->required()
                    ->native(false),
                Select::make('parent_id')
                    ->label('Parent retailer')
                    ->options($parentOptions)
                    ->searchable()
                    ->nullable()
                    ->visible(fn (Get $get): bool => (string) $get('type') === '4')
                    ->native(false),
                TextInput::make('tax')
                    ->numeric()
                    ->nullable(),
                TextInput::make('tax_number')
                    ->nullable(),
            ])
            ->model(RetailerMeta::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = User::create([
            'name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => 5,
        ]);

        $meta = $user->retailer()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'tax' => $data['tax'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'type' => $data['type'],
        ]);

        $mailData = [
            'subject' => 'A retailer account has been created',
            'body' => 'welcome to iziibuy. A new retailer account has been created.',
            'button_link' => route('login'),
            'button_text' => 'Login',
            'emails' => [],
        ];
        Mail::to($user->email)->send(new NotificationEmail($mailData));

        return $meta;
    }
}
