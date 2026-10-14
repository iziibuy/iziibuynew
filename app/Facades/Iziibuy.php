<?php

namespace App\Facades;

use App\Currency\Currency;
use App\Models\Shop;
use App\Support\LegacyVoyager\VoyagerFacade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Iziibuy
{
    public function round_num($price)
    {
        // return sprintf('%.2f', $price);
        return round((float) $price, 2);
    }

    public function price($price, $shop = null, $currency = null)
    {
        if ($currency) {
            $default = $currency;
            $current = $currency;
        } else {
            if (request()->user_name) {
                $shop = $this->getShop();
                $default = $shop->default_currency;
            } elseif (isset(auth()->user()->shop->default_currency)) {
                $default = auth()->user()->shop->default_currency;
            } else {
                $default = 'NOK';
            }
            $current = session()->get('current_currency')[request()->user_name] ?? $default;
        }

        return $this->round_num(Currency::convert($current, $price, $default)).' '.$current;
    }

    public function onlyPrice($price, $shop = null, $currency = null)
    {
        $default = Shop::where('user_name', request()->user_name)->first()->default_currency ?? auth()->user()->shop->default_currency ?? $shop;
        $current = $currency ?? session()->get('current_currency')[request()->user_name] ?? $default;

        return $this->round_num(Currency::convert($current ?? $default, $price, $default));
    }

    public function withSymbol($price, $symbol = 'NOK')
    {
        return $this->round_num($price).' '.$symbol;
    }

    public function currency($shop = null)
    {
        $model = Cache::remember('shop-'.request()->user_name, 900, function () {
            return Shop::where('user_name', request()->user_name)->first();
        });
        $default = Cache::remember('default_currency:'.request()->user_name, 5, function () use ($shop, $model) {
            return $model->default_currency
                ?? auth()->user()->shop->default_currency
                ?? @$shop->default_currency;
        });

        $current = session()->get('current_currency')[request()->user_name] ?? $default;

        return $current;
    }

    public function tax()
    {
        return Cart::session(request('user_name'))->getContent()->map(function ($product) {

            return $product->model->taxAmount * $product->quantity;
        })->sum();
    }

    public function basePrice()
    {
        return Cart::session(request('user_name'))->getContent()->map(function ($product) {
            return $product->model->originalPrice * $product->quantity;
        })->sum();
    }

    public function newTotal()
    {
        return $this->newSubtotal() + $this->shipping();
    }

    public function isEnterprise()
    {
        return env('enterprise') ? true : false;
    }

    public function quickpay_api_key()
    {
        return config('services.quickpay.api_key', '');
    }

    public function newSubtotal()
    {
        return Cart::session(request()->user_name)->getTotal() - $this->discount();
    }

    public function discount()
    {
        if (session()->has('discount_'.request()->user_name)) {
            return session()->get('discount_'.request()->user_name);
        }

        return 0;
    }

    public function discount_code()
    {
        if (session()->has('discount_code_'.request()->user_name)) {
            return session()->get('discount_code_'.request()->user_name);
        }

        return null;
    }

    public function shipping_method()
    {
        return null;
    }

    public function shipping()
    {
        return 0;
    }

    // public function onlyPrice($price, $shop = null)
    // {
    //     return  $this->round_num($price);
    // }

    public function average_rating($ratings)
    {
        if ($ratings->count() > 0) {
            return $ratings->sum('rating') / $ratings->count();
        }

        return 0.00;
    }

    public function userNameToId($user_name)
    {
        $shop = Shop::where('user_name', $user_name)->first();
        if ($shop) {
            return $shop->id;
        }

        return null;
    }

    public function idToUserName($id)
    {
        $shop = Shop::where('id', $id)->first();
        if ($shop) {
            return $shop->user_name;
        }

        return null;
    }

    public function money_format($number)
    {
        return number_format($number, 0, ',', ' ');
    }

    public function image($file, $default = '')
    {

        if (filter_var($file, FILTER_VALIDATE_URL)) {
            return $file;
        } else {
            if (! empty($file)) {
                return str_replace('\\', '/', VoyagerFacade::image($file));
            }

            return $file;
        }
    }

    public function needToCharge($amount, ?Carbon $from = null, ?Carbon $until = null): float
    {
        $from = ($from ?? Carbon::now())->copy()->startOfDay();
        $until = ($until ?? Carbon::now()->copy()->endOfMonth())->copy()->startOfDay();
        $daysInMonth = $from->daysInMonth;
        $daysInPeriod = $from->diffInDays($until) + 1;

        return $this->round_num((($amount) / $daysInMonth) * $daysInPeriod);
    }

    public function qrcode($url)
    {
        if (env('APP_DEBUG') == false) {
            $path = storage_path('app/public/qrcode');
            if (! File::exists($path)) {
                File::makeDirectory($path);
            }
            $identifier = Str::uuid().'.png';
            // $qrcode = 'app/public/qrcode/' . $identifier;
            $image = QrCode::format('png')->size(300)->generate($url);
            Storage::disk('s3')->put('qrcode/'.$identifier, $image);

            return 'qrcode/'.$identifier;
        } else {
            return '';
        }
    }

    public function getShop()
    {
        if (request()->user_name) {
            $cacheKey = 'shop-'.request()->user_name;
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            } else {
                $shop = Cache::remember('shop-'.request()->user_name, 900, function () {
                    return Shop::where('user_name', request()->user_name)->first();
                });
            }
        }

        return null;
    }

    public function resetShop(Shop $shop)
    {

        Cache::forget('shop-'.$shop->user_name);
        Cache::remember('shop-'.$shop->user_name, 900, function () use ($shop) {

            return Shop::where('user_name', $shop->user_name)->first();
        });
    }

    public function isSubset(array $subset, array $superset)
    {

        $status = false;
        foreach ($superset as $set) {

            if (in_array($set, $subset)) {
                $status = true;
                break;
            }
        }

        return $status;
    }
}
