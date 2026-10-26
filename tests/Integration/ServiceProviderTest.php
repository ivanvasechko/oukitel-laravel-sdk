<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\Contracts\OukitelCloud;
use IvanVasechko\Oukitel\Contracts\SessionStore;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\RefreshUnavailable;
use IvanVasechko\Oukitel\Laravel\Facades\Oukitel;
use IvanVasechko\Oukitel\Laravel\OukitelManager;
use IvanVasechko\Oukitel\Laravel\OukitelServiceProvider;
use IvanVasechko\Oukitel\Laravel\Tests\TestCase;
use IvanVasechko\Oukitel\OukitelClient;
use IvanVasechko\Oukitel\Testing\FakeOukitelCloud;

uses(TestCase::class);

it('composes and scopes the full graph without retaining facade roots', function (): void {
    $manager = app(OukitelManager::class);
    expect(Oukitel::getFacadeRoot())->toBe($manager);
    expect(app(OukitelManager::class))->toBe($manager);
    $cloud = app(OukitelCloud::class);
    $client = app(OukitelClient::class);
    $this->app->forgetScopedInstances();
    expect(app(OukitelManager::class))->not->toBe($manager);
    expect(Oukitel::getFacadeRoot())->toBe(app(OukitelManager::class));
    expect(app(OukitelCloud::class))->not->toBe($cloud);
    expect(app(OukitelClient::class))->not->toBe($client);
});

it('honors custom clouds and returns independent account handles', function (): void {
    $fake = (new FakeOukitelCloud)->withDevices([]);
    $this->app->instance(OukitelCloud::class, $fake);
    expect(Oukitel::cloud())->toBe($fake);
    $first = Oukitel::account(['login' => 'first@example.test', 'password' => 'example-one']);
    $second = Oukitel::account(new Credentials(Region::Us, 'second@example.test', 'example-two'));
    expect($first)->not->toBe($second);
    $first->verify();
    $second->verify();
    expect($first->devices())->toBe([]);
    expect($second->devices())->toBe([]);
});

it('honors existing contract bindings when registering', function (): void {
    $original = app(SessionStore::class);
    $this->app->instance(SessionStore::class, $original);
    (new OukitelServiceProvider($this->app))->register();
    expect(app(SessionStore::class))->toBe($original);
});

it('fails unsupported refresh before HTTP', function (): void {
    config()->set('oukitel.session.use_refresh_token', true);
    expect(fn () => app(OukitelManager::class))->toThrow(RefreshUnavailable::class);
});

it('validates credential inputs without exposing supplied secrets', function (array $credentials): void {
    expect(fn () => Oukitel::account($credentials))->toThrow(InvalidArgumentException::class);
})->with([
    [[]], [['region' => 'bad', 'login' => 'example', 'password' => 'secret']],
    [['login' => '', 'password' => 'secret']], [['login' => "bad\nlogin", 'password' => 'secret']],
    [['login' => 'example', 'password' => '']],
]);

it('publishes serializable configuration under its dedicated tag', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'oukitel-config', '--force' => true])->assertSuccessful();
    expect(is_file(config_path('oukitel.php')))->toBeTrue();
    $this->artisan('config:cache')->assertSuccessful();
    expect(is_file($this->app->getCachedConfigPath()))->toBeTrue();
    $this->artisan('config:clear')->assertSuccessful();
    unlink(config_path('oukitel.php'));
});
