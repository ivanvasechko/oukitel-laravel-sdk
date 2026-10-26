<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\CacheFailed;
use IvanVasechko\Oukitel\Laravel\Cache\LaravelSessionStore;
use IvanVasechko\Oukitel\Laravel\Tests\TestCase;

uses(TestCase::class);

it('round-trips sessions, isolates prefixes and deletes missing entries safely', function (): void {
    $cache = new Repository(new ArrayStore);
    $first = new LaravelSessionStore($cache, 'first');
    $other = new LaravelSessionStore($cache, 'other');
    $session = new Session(Region::Eu, 'synthetic-access', 'synthetic-refresh', 2000000000);
    $first->delete('missing');
    $first->put('session', $session, 60);
    expect($first->get('session'))->toEqual($session);
    expect($other->get('session'))->toBeNull();
    $first->delete('session');
    expect($first->get('session'))->toBeNull();
    $first->put('session', $session, 60);
    $first->coolDown('cooldown', 30);
    expect($first->isCoolingDown('cooldown'))->toBeTrue();
    expect($other->isCoolingDown('cooldown'))->toBeFalse();
    $this->travel(61)->seconds();
    expect($first->get('session'))->toBeNull();
    expect($first->isCoolingDown('cooldown'))->toBeFalse();
});

it('retains newer atomic owners when stale owners release', function (): void {
    $cache = new Repository(new ArrayStore);
    $first = new LaravelSessionStore($cache);
    $second = new LaravelSessionStore($cache);
    $owner = $first->acquireRefreshLock('lease', 30);
    expect($owner)->toBeString();
    expect($second->acquireRefreshLock('lease', 30))->toBeNull();
    $second->releaseRefreshLock('lease', 'wrong-owner');
    expect($second->acquireRefreshLock('lease', 30))->toBeNull();
    $this->travel(31)->seconds();
    $newOwner = $second->acquireRefreshLock('lease', 30);
    expect($newOwner)->toBeString()->not->toBe($owner);
    $first->releaseRefreshLock('lease', $owner);
    expect($first->acquireRefreshLock('lease', 30))->toBeNull();
    $second->releaseRefreshLock('lease', $newOwner);
    expect($first->acquireRefreshLock('lease', 30))->toBeString();
});

it('uses best-effort ownership only for stores without lock support', function (): void {
    $backend = new class implements Store
    {
        private ArrayStore $values;

        public function touch($key, $seconds)
        {
            $value = $this->values->get($key);

            return $value !== null && $this->values->put($key, $value, $seconds);
        }

        public function __construct()
        {
            $this->values = new ArrayStore;
        }

        public function get($key)
        {
            return $this->values->get($key);
        }

        public function many(array $keys)
        {
            return $this->values->many($keys);
        }

        public function put($key, $value, $seconds)
        {
            return $this->values->put($key, $value, $seconds);
        }

        public function putMany(array $values, $seconds)
        {
            return $this->values->putMany($values, $seconds);
        }

        public function increment($key, $value = 1)
        {
            return $this->values->increment($key, $value);
        }

        public function decrement($key, $value = 1)
        {
            return $this->values->decrement($key, $value);
        }

        public function forever($key, $value)
        {
            return $this->values->forever($key, $value);
        }

        public function forget($key)
        {
            return $this->values->forget($key);
        }

        public function flush()
        {
            return $this->values->flush();
        }

        public function getPrefix()
        {
            return '';
        }
    };
    $store = new LaravelSessionStore(new Repository($backend));
    $owner = $store->acquireRefreshLock('fallback', 30);
    expect($owner)->toBeString();
    expect($store->acquireRefreshLock('fallback', 30))->toBeNull();
    $store->releaseRefreshLock('fallback', 'wrong');
    expect($store->acquireRefreshLock('fallback', 30))->toBeNull();
    $store->releaseRefreshLock('fallback', $owner);
    expect($store->acquireRefreshLock('fallback', 30))->toBeString();
});

it('surfaces backend failures and refuses invalid lease lifetimes', function (): void {
    $backend = new class extends ArrayStore
    {
        public function get($key)
        {
            throw new RuntimeException('secret-backend-context');
        }
    };
    $store = new LaravelSessionStore(new Repository($backend));
    expect(fn () => $store->get('bad'))->toThrow(CacheFailed::class);
    expect(fn () => $store->delete('bad'))->toThrow(CacheFailed::class);
    expect(fn () => $store->acquireRefreshLock('lease', 0))->toThrow(InvalidArgumentException::class);
});

it('never falls back after a supported lock backend fails', function (): void {
    $backend = new class extends ArrayStore
    {
        public function lock($name, $seconds = 0, $owner = null)
        {
            throw new RuntimeException('private-backend-detail');
        }
    };
    $store = new LaravelSessionStore(new Repository($backend));
    expect(fn () => $store->acquireRefreshLock('lease', 30))->toThrow(CacheFailed::class);
});
