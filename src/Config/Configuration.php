<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel\Config;

use Illuminate\Support\Arr;
use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\Config\RegionConfiguration;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\ConfigurationMissing as CoreConfigurationMissing;
use IvanVasechko\Oukitel\Laravel\Exceptions\ConfigurationMissing;
use SensitiveParameter;

final readonly class Configuration
{
    /** @param array<string, RegionConfiguration> $regions */
    private function __construct(
        public ClientOptions $options,
        public array $regions,
        public ?string $cacheStore,
        public string $cachePrefix,
        public ?string $logChannel,
        public ?string $probeLogin,
        #[SensitiveParameter] private ?string $password,
    ) {}

    public static function fromArray(#[SensitiveParameter] mixed $values): self
    {
        if (! is_array($values)) {
            throw new ConfigurationMissing('config');
        }
        foreach (['regions', 'http', 'session', 'logging', 'probe'] as $section) {
            if (array_key_exists($section, $values) && ! is_array($values[$section])) {
                throw new ConfigurationMissing($section);
            }
        }
        $regions = [];
        foreach (Region::cases() as $region) {
            $key = 'regions.'.$region->value;
            if (Arr::has($values, $key) && ! is_array(Arr::get($values, $key))) {
                throw new ConfigurationMissing($key);
            }
            $preset = RegionConfiguration::preset($region);
            $fields = [
                'base_url' => $preset->baseUrl, 'app_id' => $preset->appId,
                'app_version' => $preset->appVersion, 'user_domain' => $preset->userDomain,
                'user_domain_secret' => $preset->userDomainSecret,
            ];
            foreach ($fields as $field => $default) {
                $fields[$field] = self::text($values, 'regions.'.$region->value.'.'.$field) ?? $default;
            }
            try {
                $regions[$region->value] = RegionConfiguration::fromArray($fields);
            } catch (CoreConfigurationMissing) {
                throw new ConfigurationMissing('regions.'.$region->value.'.base_url');
            }
        }
        $refresh = Arr::get($values, 'session.use_refresh_token', false);
        if (! is_bool($refresh)) {
            throw new ConfigurationMissing('session.use_refresh_token');
        }
        $password = Arr::get($values, 'probe.password');
        if ($password !== null && ! is_string($password)) {
            throw new ConfigurationMissing('probe.password');
        }

        return new self(
            new ClientOptions(
                timeoutSeconds: self::integer($values, 'http.timeout_seconds', 10, 1),
                connectTimeoutSeconds: self::integer($values, 'http.connect_timeout_seconds', 3, 1),
                retryTimes: self::integer($values, 'http.retry_times', 3, 1),
                retryDelayMilliseconds: self::integer($values, 'http.retry_delay_milliseconds', 250),
                userAgent: self::text($values, 'http.user_agent') ?? 'okhttp/4.9.3',
                refreshBeforeSeconds: self::integer($values, 'session.refresh_before_seconds', 120),
                loginFailureCooldownMinutes: self::integer($values, 'session.login_failure_cooldown_minutes', 5),
                useRefreshToken: $refresh,
            ),
            $regions,
            self::text($values, 'session.cache_store'),
            self::text($values, 'session.cache_prefix') ?? 'oukitel',
            self::text($values, 'logging.channel'),
            self::text($values, 'probe.login'),
            $password === '' ? null : $password,
        );
    }

    public function probePassword(): ?string
    {
        return $this->password;
    }

    /** @return array{configuration: string} */
    public function __debugInfo(): array
    {
        return ['configuration' => '[redacted]'];
    }

    /** @param array<array-key, mixed> $values */
    private static function text(array $values, string $key): ?string
    {
        $value = Arr::get($values, $key);
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || preg_match('/[\r\n]/', $value) === 1) {
            throw new ConfigurationMissing($key);
        }

        return trim($value) === '' ? null : $value;
    }

    /** @param array<array-key, mixed> $values */
    private static function integer(array $values, string $key, int $default, int $minimum = 0): int
    {
        $value = Arr::get($values, $key, $default);
        if ((! is_int($value) && ! is_string($value))
            || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < $minimum) {
            throw new ConfigurationMissing($key);
        }

        return (int) $value;
    }
}
