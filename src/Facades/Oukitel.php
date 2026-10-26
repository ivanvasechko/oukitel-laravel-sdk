<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use IvanVasechko\Oukitel\Account;
use IvanVasechko\Oukitel\Contracts\OukitelCloud;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\Laravel\OukitelManager;

/**
 * @method static Account account(Credentials|array<array-key, mixed> $credentials)
 * @method static OukitelCloud cloud()
 *
 * @see OukitelManager
 */
final class Oukitel extends Facade
{
    protected static $cached = false;

    protected static function getFacadeAccessor(): string
    {
        return OukitelManager::class;
    }
}
