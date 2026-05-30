<?php

declare(strict_types=1);

it('is not available outside local and testing environments', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('/test/surfboard/pay-test-id')->assertNotFound();
});

it('returns validation error when platform surfboard merchant id is missing', function (): void {
    config([
        'services.surfboard.merchant_id' => '',
        'services.surfboard.store_id' => '',
    ]);

    $this->get('/test/surfboard/pay-test-id')
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
        ]);
});
