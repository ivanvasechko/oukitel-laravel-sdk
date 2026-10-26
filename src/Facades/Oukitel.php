<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel\Facades;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Facade;
use IvanVasechko\Oukitel\Account;
use IvanVasechko\Oukitel\Contracts\OukitelCloud;
use IvanVasechko\Oukitel\Contracts\SessionStore;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\Laravel\Cache\LaravelSessionStore;
use IvanVasechko\Oukitel\Laravel\OukitelManager;
use IvanVasechko\Oukitel\Laravel\Testing\OukitelFake;
use IvanVasechko\Oukitel\OukitelClient;
use IvanVasechko\Oukitel\Session\SessionManager;
use IvanVasechko\Oukitel\Testing\FakeOukitelCloud;
use Psr\Clock\ClockInterface;
use RuntimeException;

/**
 * @method static Account account(Credentials|array<array-key, mixed> $credentials)
 * @method static OukitelCloud cloud()
 *
 * @see OukitelManager
 */
final class Oukitel extends Facade
{
    protected static $cached = false;

    public static function fake(): OukitelFake
    {
        $app = self::$app ?? throw new RuntimeException('Boot Laravel before enabling the Oukitel fake.');
        $fake = new OukitelFake(new FakeOukitelCloud($app->make(ClockInterface::class)));
        $app->instance(OukitelCloud::class, $fake->cloud());
        $app->instance(SessionStore::class, new LaravelSessionStore(new Repository(new ArrayStore)));
        foreach ([SessionManager::class, OukitelClient::class, OukitelManager::class] as $service) {
            $app->forgetInstance($service);
        }
        self::clearResolvedInstance(OukitelManager::class);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return OukitelManager::class;
    }
}
