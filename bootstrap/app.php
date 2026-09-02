<?php

use App\Http\Middleware\BlockAccess;
use App\Http\Middleware\CartIsEmpty;
use App\Http\Middleware\checkMaster;
use App\Http\Middleware\CheckYourRoleHasPermission;
use App\Http\Middleware\EnsureUserIsAdminStaff;
use App\Http\Middleware\EnterprisePaid;
use App\Http\Middleware\ExternalPaid;
use App\Http\Middleware\ExtractUsernameFromRoute;
use App\Http\Middleware\Localization;
use App\Http\Middleware\Paid;
use App\Http\Middleware\PaymentMethodAccessPaymentCheck;
use App\Http\Middleware\PermisssionMiddleware;
use App\Http\Middleware\PersonalClientMiddleware;
use App\Http\Middleware\PersonalTrainerMiddleware;
use App\Http\Middleware\ProtectedLink;
use App\Http\Middleware\ShopCheck;
use App\Http\Middleware\ShopValidation;
use App\Http\Middleware\SubscribedForService;
use App\Http\Middleware\VoyagerBreadPermission;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('subscription:notify')->monthlyOn(27);

        // Shop, Enterprise and Plugin (PaymentMethodAccess) subscriptions all
        // charge on the 1st, retry on the 3rd if still active, then deactivate
        // on the 5th if the retry also failed.
        $schedule->command('monthly:charge 1')->monthlyOn(1);
        $schedule->command('monthly:charge 1')->monthlyOn(3);
        $schedule->command('monthly:charge 0')->monthlyOn(5);

        $schedule->command('enterprise:payment 1')->monthlyOn(1);
        $schedule->command('enterprise:payment 1')->monthlyOn(3);
        $schedule->command('enterprise:payment 0')->monthlyOn(5);

        $schedule->command('payment-method-access:charge 1')->monthlyOn(1);
        $schedule->command('payment-method-access:charge 1')->monthlyOn(3);
        $schedule->command('payment-method-access:charge 0')->monthlyOn(5);

        $schedule->command('credits:expire')->daily();
        $schedule->command('bonus:calculate')->monthlyOn(now()->endOfMonth()->format('d') - 1, '23:59');
        $schedule->command('booking:complete')->everyFifteenMinutes();
        $schedule->command('shops:remove')->everyFifteenMinutes();
        $schedule->command('queue:work --stop-when-empty')->everyMinute();
        $schedule->command('subscription:autorenew')->daily();
        $schedule->command('external-button-subscriptions:charge')->daily();
        $schedule->command('order:cancel')->everyMinute();
        $schedule->command('charges:check')->everyFiveMinutes();
        $schedule->command('shops:process')->hourly();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([
            ExtractUsernameFromRoute::class,
        ]);
        $middleware->web(append: [
            Localization::class,
        ]);
        $middleware->alias([
            'admin.user' => EnsureUserIsAdminStaff::class,
            'role' => CheckYourRoleHasPermission::class,
            'permission' => PermisssionMiddleware::class,
            'shopCheck' => ShopCheck::class,
            'canProvideService' => SubscribedForService::class,
            'isPersonalTrainer' => PersonalTrainerMiddleware::class,
            'personalClient' => PersonalClientMiddleware::class,
            'Paid' => Paid::class,
            'ExternalPaid' => ExternalPaid::class,
            'EnterprisePaid' => EnterprisePaid::class,
            'cartIsntEmpty' => CartIsEmpty::class,
            'checkShop' => ShopValidation::class,
            'checkMaster' => checkMaster::class,
            'checkIfPaid' => PaymentMethodAccessPaymentCheck::class,
            'protectedLink' => ProtectedLink::class,
            'BlockAccess' => BlockAccess::class,
            'voyager.permission' => VoyagerBreadPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
