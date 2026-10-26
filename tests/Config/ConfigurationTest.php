<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use IvanVasechko\Oukitel\Config\RegionConfiguration;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Laravel\Config\Configuration;
use IvanVasechko\Oukitel\Laravel\Exceptions\ConfigurationMissing;

it('uses presets for omitted and empty region overrides with refresh disabled', function (): void {
    foreach ([null, '', '  '] as $value) {
        $settings = Configuration::fromArray(['regions' => ['EU' => ['app_id' => $value]]]);
        expect($settings->regions['EU']->appId)->toBe(RegionConfiguration::preset(Region::Eu)->appId);
        expect($settings->options->useRefreshToken)->toBeFalse();
    }
});

it('accepts typed options and preserves configured password bytes', function (): void {
    $settings = Configuration::fromArray([
        'regions' => ['EU' => ['base_url' => 'https://cloud.example.test', 'app_id' => 'example-app']],
        'http' => ['timeout_seconds' => '20', 'retry_delay_milliseconds' => 0],
        'session' => ['cache_store' => 'array', 'cache_prefix' => 'test', 'use_refresh_token' => true],
        'logging' => ['channel' => 'null'],
        'probe' => ['login' => 'user@example.test', 'password' => " space \n"],
    ]);
    expect($settings->regions['EU']->baseUrl)->toBe('https://cloud.example.test');
    expect($settings->options->timeoutSeconds)->toBe(20);
    expect($settings->options->retryDelayMilliseconds)->toBe(0);
    expect($settings->options->useRefreshToken)->toBeTrue();
    expect($settings->probePassword())->toBe(" space \n");
    expect(print_r($settings, true))->not->toContain('space', 'user@example.test');
});

it('rejects invalid configuration without echoing its value', function (string $key, mixed $value): void {
    $values = [];
    Arr::set($values, $key, $value);
    expect(fn () => Configuration::fromArray($values))->toThrow(ConfigurationMissing::class, 'oukitel.'.$key);
})->with([
    ['http.timeout_seconds', 0], ['http.connect_timeout_seconds', -1],
    ['http.retry_times', 'wrong'], ['http.retry_delay_milliseconds', -1],
    ['session.refresh_before_seconds', []], ['session.login_failure_cooldown_minutes', true],
    ['session.use_refresh_token', 'true'], ['regions.EU.app_id', 5],
    ['regions.EU.base_url', 'secret-invalid-url'], ['logging.channel', "bad\nvalue"],
    ['probe.password', []],
    ['http', false], ['session', null], ['logging', 'bad'], ['probe', 42],
    ['regions', 'invalid'], ['regions.EU', 'invalid'],
]);

it('rejects a non-array config and treats empty probe password as absent', function (): void {
    expect(fn () => Configuration::fromArray(false))->toThrow(ConfigurationMissing::class);
    expect(Configuration::fromArray(['probe' => ['password' => '']])->probePassword())->toBeNull();
});
