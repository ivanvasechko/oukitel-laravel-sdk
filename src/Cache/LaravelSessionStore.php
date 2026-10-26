<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel\Cache;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;
use IvanVasechko\Oukitel\Contracts\SessionStore;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\Exceptions\CacheFailed;
use IvanVasechko\Oukitel\Session\Psr16SessionStore;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheException;
use RuntimeException;
use SensitiveParameter;

final readonly class LaravelSessionStore implements SessionStore
{
    private Psr16SessionStore $sessions;

    public function __construct(
        private Repository $cache,
        private string $prefix = 'oukitel',
        LoggerInterface $logger = new NullLogger,
    ) {
        $this->sessions = new Psr16SessionStore($cache, $logger);
    }

    public function get(string $key): ?Session
    {
        return $this->sessions->get($this->key($key));
    }

    public function put(string $key, #[SensitiveParameter] Session $session, int $ttl): void
    {
        $this->sessions->put($this->key($key), $session, $ttl);
    }

    public function delete(string $key): void
    {
        $key = $this->key($key);
        if ($this->access(fn (): bool => $this->cache->has($key))) {
            $this->sessions->delete($key);
        }
    }

    public function isCoolingDown(string $key): bool
    {
        return $this->sessions->isCoolingDown($this->key($key));
    }

    public function coolDown(string $key, int $ttl): void
    {
        $this->sessions->coolDown($this->key($key), $ttl);
    }

    public function acquireRefreshLock(string $key, int $ttl): ?string
    {
        if ($ttl < 1) {
            throw new InvalidArgumentException('Refresh lock TTL must be positive.');
        }
        $backend = $this->cache->getStore();
        $key = $this->key($key);
        if (! $backend instanceof LockProvider) {
            return $this->sessions->acquireRefreshLock($key, $ttl);
        }

        return $this->access(function () use ($backend, $key, $ttl): ?string {
            $lock = $backend->lock($key, $ttl);

            return $lock->get() ? $lock->owner() : null;
        });
    }

    public function releaseRefreshLock(string $key, string $owner): void
    {
        $backend = $this->cache->getStore();
        $key = $this->key($key);
        if (! $backend instanceof LockProvider) {
            $this->sessions->releaseRefreshLock($key, $owner);

            return;
        }
        $this->access(fn (): bool => $backend->restoreLock($key, $owner)->release());
    }

    private function key(string $key): string
    {
        return hash('sha256', $this->prefix.'|'.$key);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    private function access(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (CacheException|RuntimeException) {
            throw new CacheFailed;
        }
    }
}
