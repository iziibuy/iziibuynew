<?php

namespace App\Imports;

use App\Mail\ShopImport;
use App\Models\Enterprise;
use App\Models\Shop;
use App\Models\User;
use App\Payment\Subscribe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Iziibuy;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use RuntimeException;

class ShopsImport implements ToCollection, WithHeadingRow
{
    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $enterprise = Enterprise::first();

        if ($enterprise === null) {
            throw new RuntimeException('Enterprise configuration is missing.');
        }

        $api = setting('payment.api_key');
        $quickpay = new Subscribe($api);
        $fee = Iziibuy::needToCharge((($enterprise->subscriptionFee() * count($rows)) * 1.25));
        $charge = $quickpay->subscription($enterprise->subscription_id)->charge($fee);

        if ($charge['status'] == false) {
            throw new RuntimeException('Failed to create charge');
        }
        foreach ($rows as $row) {
            $password = rand(60000, 999999);
            DB::beginTransaction();

            $user = User::updateOrCreate(['email' => $row['email']], [
                'name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'role_id' => 3,
                'email' => $row['email'],
                'phone' => $row['phone'],
                'password' => Hash::make($password),
            ]);
            $shop = Shop::updateOrCreate(['user_id' => $user->id, 'user_name' => $row['user_name']], [
                'user_name' => $row['user_name'],
                'terms' => File::get(public_path('terms.txt')),
                'user_id' => $user->id,
                'status' => 1,
                'establishment' => 1,
            ]);
            $shop->createMetas([
                'name' => $row['shop_name'],
                'company_name' => $row['company_name'],
                'company_registration' => $row['company_registration'],
                'city' => $row['city'],
                'street' => $row['street'],
                'post_code' => $row['post_code'],
                'contact_email' => $row['contact_email'],
                'contact_phone' => $row['contact_phone'],
                'logo' => 'defaults/shop_default_logo.png',
                'status' => 1,
            ]);

            $user->update([
                'shop_id' => $user->shop->id,
            ]);

            $mail_data = [
                'subject' => 'A new shop has been created',
                'body' => 'Welcome to iziibuy. A new shop account has been created. Please click the link below to login <br> Your password is: '.$password.'<br> Please change the password when you logged in',
                'button_link' => route('login'),
                'button_text' => 'Login',
                'emails' => [],
            ];
            Mail::to($user->email)->send(new ShopImport($mail_data));
            DB::commit();
        }
    }
    // public function chunkSize(): int
    // {
    //     return 50;
    // }
}
