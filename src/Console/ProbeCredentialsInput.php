<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel\Console;

use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\Enums\Region;
use SensitiveParameter;

final class ProbeCredentialsInput
{
    /** @param resource $stream */
    public static function fromStream(#[SensitiveParameter] mixed $stream, Region $region, ?string $login = null): Credentials
    {
        if (! is_resource($stream)) {
            throw new ProbeInputInvalid('Credential input stream is unavailable.');
        }
        $login ??= self::line($stream);

        return self::validated($region, $login, self::line($stream));
    }

    public static function validated(Region $region, #[SensitiveParameter] mixed $login, #[SensitiveParameter] mixed $password): Credentials
    {
        if (! is_string($login) || trim($login) === '' || preg_match('/[\x00-\x1f\x7f]/', $login) === 1
            || ! is_string($password) || $password === '') {
            throw new ProbeInputInvalid('Provide a non-empty login and password using one credential input mode.');
        }

        return new Credentials($region, $login, $password);
    }

    /** @param resource $stream */
    private static function line(#[SensitiveParameter] mixed $stream): string
    {
        $line = fgets($stream);
        if ($line === false) {
            throw new ProbeInputInvalid('Credential input ended before a complete login and password were provided.');
        }

        return rtrim($line, "\r\n");
    }
}
