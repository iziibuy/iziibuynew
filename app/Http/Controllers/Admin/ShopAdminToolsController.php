<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NotificationEmail;
use App\Models\Shop;
use App\Models\Shop as ModelsShop;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ShopAdminToolsController extends Controller
{
    public function sendShopPassword(ModelsShop $shop): RedirectResponse
    {
        $password = rand(9999, 99999999);
        $mail_data = [
            'subject' => 'Ny Digital webshop er opprettet',
            'body' => 'En ny konto er opprettet hos Iziibuy. Vennligst logg in med dine detaljer og bekreft betalingen ved å følge linken under for å aktivere din webshop.<br> Email: '.$shop->user->email.'<br> Password: '.$password.'<br> Betalings link: '.route('shop.subscription.payment'),
            'button_link' => route('shop.subscription.payment'),
            'button_text' => 'Betal Nå',
            'emails' => [],
        ];
        $shop->user()->update([
            'password' => Hash::make($password),
        ]);
        Mail::to($shop->user->email)->send(new NotificationEmail($mail_data));

        return back()->with([
            'message' => 'Password and payment link sent successfully',
            'alert-type' => 'success',
        ]);
    }

    public function advanceShopEdit(Shop $shop): View
    {
        $shop->load('user');

        return view('admin.shops.advance-edit', [
            'shop' => $shop,
        ]);
    }
}
