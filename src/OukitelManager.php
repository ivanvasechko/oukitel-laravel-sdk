<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel;

use InvalidArgumentException;
use IvanVasechko\Oukitel\Account;
use IvanVasechko\Oukitel\Contracts\OukitelCloud;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\OukitelClient;
use SensitiveParameter;

final readonly class OukitelManager
{
    public function __construct(
        private OukitelClient $client,
        private OukitelCloud $cloud,
    ) {}

    /** @param Credentials|array<array-key, mixed> $credentials */
    public function account(#[SensitiveParameter] Credentials|array $credentials): Account
    {
        if (is_array($credentials)) {
            $region = $credentials['region'] ?? Region::Eu;
            $region = $region instanceof Region ? $region : (is_string($region) ? Region::tryFrom(strtoupper($region)) : null);
            $login = $credentials['login'] ?? null;
            $password = $credentials['password'] ?? null;
            if ($region === null || ! is_string($login) || ! is_string($password)) {
                throw new InvalidArgumentException('Oukitel credentials require a valid region, login and password.');
            }
            $credentials = new Credentials($region, $login, $password);
        }
        if (trim($credentials->login) === '' || preg_match('/[\r\n]/', $credentials->login) === 1 || $credentials->password === '') {
            throw new InvalidArgumentException('Oukitel credentials require a non-empty login and password.');
        }

        return $this->client->account($credentials);
    }

    public function cloud(): OukitelCloud
    {
        return $this->cloud;
    }
}
