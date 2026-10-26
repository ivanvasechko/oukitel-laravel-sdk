<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\Exceptions\ApiError;
use IvanVasechko\Oukitel\Laravel\Facades\Oukitel;
use IvanVasechko\Oukitel\Laravel\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set('oukitel.regions.EU.base_url', 'https://cloud.example.test');
    config()->set('oukitel.http.retry_delay_milliseconds', 0);
});

it('uses native HTTP fakes through login, cached devices, telemetry and wake', function (): void {
    Http::fake([
        'https://cloud.example.test/v2/enduser/*' => Http::response(['code' => 200, 'data' => [
            'accessToken' => ['token' => 'synthetic-access', 'expirationTime' => time() + 3600],
            'refreshToken' => ['token' => 'synthetic-refresh'],
        ]]),
        'https://cloud.example.test/*userDeviceList' => Http::response(['code' => 200, 'data' => ['list' => []]]),
        'https://cloud.example.test/*getDeviceBusinessAttributes*' => Http::response(['code' => 200, 'data' => [
            'deviceData' => ['onlineStatus' => 1],
            'customizeTslInfo' => [['resourceCode' => 'battery_percentage', 'resourceValce' => '52', 'dataType' => 'INT']],
        ]]),
        'https://cloud.example.test/*batchControlDevice' => Http::response(['code' => 200]),
    ]);
    $account = Oukitel::account(['login' => 'reader@example.test', 'password' => 'synthetic-password']);
    $device = new DeviceId('synthetic-device', 'synthetic-product');
    expect($account->devices())->toBe([]);
    expect($account->devices())->toBe([]);
    expect($account->telemetry($device)->batteryPercent)->toBe(52);
    expect($account->rawAttributes($device)[0]->resourceValue)->toBe('52');
    $account->wake($device);
    Http::assertSentCount(6);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'getDeviceBusinessAttributes')
        && $request->header('Authorization') === ['synthetic-access']);
    $logins = Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'emailPwdLogin'));
    expect($logins)->toHaveCount(1);
});

it('keeps all read retries in the core and never replays failed controls', function (): void {
    Http::fake([
        'https://cloud.example.test/v2/enduser/*' => Http::response(['code' => 200, 'data' => [
            'accessToken' => ['token' => 'synthetic-access', 'expirationTime' => time() + 3600],
            'refreshToken' => ['token' => 'synthetic-refresh'],
        ]]),
        'https://cloud.example.test/*userDeviceList' => Http::sequence()->push('', 503)->push('', 503)->push(['code' => 200, 'data' => ['list' => []]]),
        'https://cloud.example.test/*batchControlDevice' => Http::response('', 503),
    ]);
    $account = Oukitel::account(['login' => 'reader@example.test', 'password' => 'synthetic-password']);
    expect($account->devices())->toBe([]);
    expect(fn () => $account->wake(new DeviceId('synthetic-device', 'synthetic-product')))->toThrow(ApiError::class);
    Http::assertSentCount(5);
});
