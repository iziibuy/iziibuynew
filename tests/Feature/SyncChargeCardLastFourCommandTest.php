<?php

declare(strict_types=1);

use App\Models\Charge;
use App\Models\Shop;
use App\Models\User;

it('lists elavon shop charges missing last4 in dry-run mode', function (): void {
    $owner = User::factory()->create();

    $shop = Shop::query()->create([
        'user_id' => $owner->id,
        'user_name' => 'sync-last4-shop',
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
    ]);

    $charge = Charge::query()->create([
        'shop_id' => $shop->id,
        'order_id' => 'wh22272hbqt3trwkx6xhb49gfkyw',
        'amount' => 748.75,
        'status' => 1,
        'comment' => 'Monthly subscription fee',
        'details' => json_encode(['shop' => ['id' => $shop->id]]),
    ]);

    $this->artisan('charges:sync-card-last4', ['--dry-run' => true, '--shop-charge' => $charge->id])
        ->expectsOutputToContain('Would sync shop charge #'.$charge->id)
        ->assertSuccessful();
});
