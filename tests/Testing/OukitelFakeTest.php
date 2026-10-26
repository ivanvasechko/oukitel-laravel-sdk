<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use IvanVasechko\Oukitel\Contracts\OukitelCloud;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\AuthenticationFailed;
use IvanVasechko\Oukitel\Laravel\Facades\Oukitel;
use IvanVasechko\Oukitel\Laravel\OukitelManager;
use IvanVasechko\Oukitel\Laravel\Tests\TestCase;
use IvanVasechko\Oukitel\OukitelClient;
use PHPUnit\Framework\AssertionFailedError;

uses(TestCase::class);

it('replaces future resolutions even after the real graph was resolved', function (): void {
    Http::preventStrayRequests();
    $previous = Oukitel::getFacadeRoot();
    $client = app(OukitelClient::class);
    $fake = Oukitel::fake()->withDevices([]);
    expect(app(OukitelManager::class))->not->toBe($previous);
    expect(app(OukitelClient::class))->not->toBe($client);
    expect(Oukitel::cloud())->toBe($fake->cloud())->toBe(app(OukitelCloud::class));
    expect(Oukitel::account(['login' => 'reader@example.test', 'password' => 'synthetic'])->devices())->toBe([]);
    $this->app->forgetScopedInstances();
    expect(Oukitel::cloud())->not->toBe($fake->cloud());
    Http::assertNothingSent();
});

it('isolates fake authentication from a previously cached session', function (): void {
    Http::preventStrayRequests();
    $credentials = ['login' => 'reader@example.test', 'password' => 'synthetic'];
    Oukitel::fake()->withDevices([]);
    expect(Oukitel::account($credentials)->devices())->toBe([]);
    Oukitel::fake()->withDevices([])->failLoginWith(new AuthenticationFailed);
    expect(fn () => Oukitel::account($credentials)->devices())->toThrow(AuthenticationFailed::class);
    Http::assertNothingSent();
});

it('records wakes separately and provides meaningful failing assertions', function (): void {
    $device = new DeviceId('synthetic-device', 'synthetic-product');
    $other = new DeviceId('other-device', 'synthetic-product');
    $fake = Oukitel::fake();
    $fake->assertNotWoke($device);
    $fake->assertNoControlsSent();
    expect(fn () => $fake->assertWoke($device))->toThrow(AssertionFailedError::class);
    Oukitel::account(['login' => 'reader@example.test', 'password' => 'synthetic'])->wake($device);
    $fake->assertWoke($device);
    $fake->assertNotWoke($other);
    $fake->assertNoControlsSent();
    expect(fn () => $fake->assertNotWoke($device))->toThrow(AssertionFailedError::class);
    $fake->cloud()->control($fake->cloud()->login(new Credentials(
        Region::Eu, 'reader@example.test', 'synthetic',
    )), $device, ['acSwitch' => true]);
    expect(fn () => $fake->assertNoControlsSent())->toThrow(AssertionFailedError::class);
});
