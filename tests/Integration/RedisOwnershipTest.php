<?php

declare(strict_types=1);

use Illuminate\Cache\CacheManager;
use IvanVasechko\Oukitel\Laravel\Cache\LaravelSessionStore;
use IvanVasechko\Oukitel\Laravel\Tests\TestCase;

uses(TestCase::class);

it('retains current Redis owners across independent stores and stale releases', function (): void {
    config()->set('cache.stores.oukitel_redis', ['driver' => 'redis', 'connection' => 'default']);
    $prefix = 'synthetic-'.bin2hex(random_bytes(12));
    $manager = app(CacheManager::class);
    $first = new LaravelSessionStore($manager->store('oukitel_redis'), $prefix);
    $manager->forgetDriver('oukitel_redis');
    $second = new LaravelSessionStore($manager->store('oukitel_redis'), $prefix);
    $owner = $first->acquireRefreshLock('lease', 30);
    expect($owner)->toBeString();
    try {
        expect($second->acquireRefreshLock('lease', 30))->toBeNull();
        $second->releaseRefreshLock('lease', 'wrong-owner');
        expect($second->acquireRefreshLock('lease', 30))->toBeNull();
        $first->releaseRefreshLock('lease', $owner);
        $newOwner = $second->acquireRefreshLock('lease', 30);
        expect($newOwner)->toBeString()->not->toBe($owner);
        try {
            $first->releaseRefreshLock('lease', $owner);
            expect($first->acquireRefreshLock('lease', 30))->toBeNull();
        } finally {
            $second->releaseRefreshLock('lease', $newOwner);
        }
    } finally {
        $first->releaseRefreshLock('lease', $owner);
    }
})->skip(getenv('OUKITEL_TEST_REDIS') !== '1', 'Requires an isolated Redis test service.');
