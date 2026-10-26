<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel;

use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider;
use IvanVasechko\Oukitel\Clock\SystemClock;
use IvanVasechko\Oukitel\Contracts\OukitelCloud;
use IvanVasechko\Oukitel\Contracts\SessionStore;
use IvanVasechko\Oukitel\Http\AcceleronixCloudClient;
use IvanVasechko\Oukitel\Laravel\Cache\LaravelSessionStore;
use IvanVasechko\Oukitel\Laravel\Config\Configuration;
use IvanVasechko\Oukitel\Laravel\Console\ProbeOukitelCommand;
use IvanVasechko\Oukitel\Laravel\Http\LaravelPsr18Client;
use IvanVasechko\Oukitel\OukitelClient;
use IvanVasechko\Oukitel\Session\SessionManager;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final class OukitelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oukitel.php', 'oukitel');
        $this->app->scopedIf(Configuration::class, fn (Application $app): Configuration => Configuration::fromArray($app->make(ConfigRepository::class)->get('oukitel')));
        $this->app->bindIf(ClockInterface::class, SystemClock::class);
        $this->app->scopedIf(LaravelPsr18Client::class, fn (Application $app): LaravelPsr18Client => new LaravelPsr18Client(
            $app->make(Factory::class), $app->make(Configuration::class)->options,
        ));
        $this->app->scopedIf(SessionStore::class, fn (Application $app): LaravelSessionStore => new LaravelSessionStore(
            $app->make(CacheManager::class)->store($app->make(Configuration::class)->cacheStore),
            $app->make(Configuration::class)->cachePrefix, $this->logger($app),
        ));
        $this->app->scopedIf(OukitelCloud::class, fn (Application $app): AcceleronixCloudClient => new AcceleronixCloudClient(
            $app->make(LaravelPsr18Client::class), new HttpFactory, new HttpFactory,
            $app->make(Configuration::class)->regions, $app->make(Configuration::class)->options,
            logger: $this->logger($app),
        ));
        $this->app->scopedIf(SessionManager::class, fn (Application $app): SessionManager => new SessionManager(
            $app->make(OukitelCloud::class), $app->make(SessionStore::class),
            $app->make(ClockInterface::class), $app->make(Configuration::class)->options,
        ));
        $this->app->scopedIf(OukitelClient::class, fn (Application $app): OukitelClient => new OukitelClient(
            $app->make(SessionManager::class), $this->logger($app), $app->make(ClockInterface::class),
        ));
        $this->app->scopedIf(OukitelManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ProbeOukitelCommand::class]);
            $this->publishes([__DIR__.'/../config/oukitel.php' => config_path('oukitel.php')], 'oukitel-config');
        }
    }

    private function logger(Application $app): LoggerInterface
    {
        $channel = $app->make(Configuration::class)->logChannel;

        return $channel === null ? $app->make(LoggerInterface::class) : $app->make(LogManager::class)->channel($channel);
    }
}
