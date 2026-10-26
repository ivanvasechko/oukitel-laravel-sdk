<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Laravel\Config\Configuration;
use IvanVasechko\Oukitel\Laravel\OukitelManager;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Input\StreamableInputInterface;

final class ProbeOukitelCommand extends Command
{
    protected $signature = 'oukitel:probe
        {--region=EU : Cloud region: EU, US or CN}
        {--login= : Account login; password is prompted invisibly}
        {--password-stdin : Read one password line from stdin}
        {--credentials-stdin : Read login and password as two stdin lines}
        {--credentials-from-config : Explicitly use configured probe credentials}
        {--device= : Device list index or unique masked suffix}
        {--watch= : Positive seconds between samples}
        {--count= : Positive number of samples; requires watch}
        {--raw : Include privacy-filtered raw attributes}
        {--capabilities : Include inferred capabilities}
        {--wake : Explicitly wake the selected device once}';

    protected $description = 'List masked devices or inspect read-only telemetry; wake only when explicitly requested';

    public function handle(): int
    {
        try {
            [$region, $watch, $count] = $this->validateOptions();
            $credentials = $this->credentials($region);
            $account = $this->laravel->make(OukitelManager::class)->account($credentials);
            $devices = $account->devices();
            foreach ($devices as $index => $device) {
                $online = $device->isOnline === null ? 'unknown' : ($device->isOnline ? 'online' : 'offline');
                $this->line(($index + 1).': Device ****'.ProbeOutput::suffix($device->id->deviceKey).' ('.$online.')');
            }
            $selector = $this->option('device');
            if ($selector === null) {
                $this->info($devices === [] ? 'No devices found.' : 'Select a listed index or unique masked suffix with --device.');

                return self::SUCCESS;
            }
            $device = $this->selectDevice($devices, $selector);
            if ($this->option('wake')) {
                $account->wake($device->id);
                $this->info('Wake requested; no state change is assumed.');
            }
            for ($sample = 1; $count === null || $sample <= $count; $sample++) {
                $output = ['sample' => $sample, 'telemetry' => ProbeOutput::telemetry($account->telemetry($device->id))];
                if ($this->option('raw')) {
                    $output['raw'] = ProbeOutput::raw($account->rawAttributes($device->id));
                }
                if ($this->option('capabilities')) {
                    $capabilities = $account->capabilities($device->id);
                    $output['capabilities'] = [
                        'readable' => array_column($capabilities->readable, 'value'),
                        'controllable' => array_column($capabilities->controllable, 'value'),
                    ];
                }
                $this->line(json_encode($output, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
                if ($watch === null || ($count !== null && $sample === $count)) {
                    break;
                }
                Sleep::for($watch)->seconds();
            }

            return self::SUCCESS;
        } catch (ProbeInputInvalid $failure) {
            $this->error($failure->getMessage());
        } catch (RuntimeException|JsonException) {
            $this->error('Oukitel request failed. Check credentials, connectivity and cache configuration.');
        }

        return self::FAILURE;
    }

    /** @return array{Region, ?int, ?int} */
    private function validateOptions(): array
    {
        $value = $this->option('region');
        $region = is_string($value) ? Region::tryFrom(strtoupper($value)) : null;
        if ($region === null) {
            throw new ProbeInputInvalid('Choose region EU, US or CN.');
        }
        $watch = $this->positive($this->option('watch'));
        $count = $this->positive($this->option('count'));
        if ($count !== null && $watch === null) {
            throw new ProbeInputInvalid('--count requires --watch.');
        }
        $modes = (int) $this->option('password-stdin') + (int) $this->option('credentials-stdin') + (int) $this->option('credentials-from-config');
        if ($modes > 1 || (($this->option('credentials-stdin') || $this->option('credentials-from-config')) && $this->option('login') !== null)) {
            throw new ProbeInputInvalid('Choose one credential input mode without conflicting login options.');
        }
        $selector = $this->option('device');
        if ($selector !== null && (! is_string($selector) || preg_match('/^[a-zA-Z0-9]+$/D', $selector) !== 1)) {
            throw new ProbeInputInvalid('Device selector must be a listed index or masked suffix.');
        }
        if ($selector === null && ($watch !== null || $this->option('wake') || $this->option('raw') || $this->option('capabilities'))) {
            throw new ProbeInputInvalid('Select --device before requesting samples, raw data, capabilities or wake.');
        }

        return [$region, $watch, $watch === null ? 1 : $count];
    }

    private function positive(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            throw new ProbeInputInvalid('Watch interval and sample count must be positive integers.');
        }

        return (int) $value;
    }

    private function credentials(Region $region): Credentials
    {
        if ($this->option('credentials-from-config')) {
            $config = $this->laravel->make(Configuration::class);

            return ProbeCredentialsInput::validated($region, $config->probeLogin, $config->probePassword());
        }
        $login = $this->option('login');
        if ($login !== null && ! is_string($login)) {
            throw new ProbeInputInvalid('Provide one account login.');
        }
        if ($this->option('credentials-stdin') || $this->option('password-stdin')) {
            if ($this->option('password-stdin') && (! is_string($login) || trim($login) === '')) {
                throw new ProbeInputInvalid('--password-stdin requires --login.');
            }
            $stream = $this->input instanceof StreamableInputInterface ? $this->input->getStream() : null;
            $owned = $stream === null;
            $stream ??= fopen('php://stdin', 'r');
            if (! is_resource($stream)) {
                throw new ProbeInputInvalid('Credential input stream is unavailable.');
            }
            try {
                return ProbeCredentialsInput::fromStream($stream, $region, $this->option('credentials-stdin') ? null : $login);
            } finally {
                if ($owned) {
                    fclose($stream);
                }
            }
        }
        if (! $this->input->isInteractive()) {
            throw new ProbeInputInvalid('Non-interactive use requires explicit stdin or configured credential mode.');
        }
        $login ??= $this->ask('Account login');

        return ProbeCredentialsInput::validated($region, $login, $this->secret('Account password'));
    }

    /** @param list<Device> $devices */
    private function selectDevice(array $devices, mixed $selector): Device
    {
        if (is_string($selector) && ctype_digit($selector) && isset($devices[(int) $selector - 1])) {
            return $devices[(int) $selector - 1];
        }
        $matches = array_values(array_filter($devices, static fn (Device $device): bool => is_string($selector)
            && strcasecmp(ProbeOutput::suffix($device->id->deviceKey), $selector) === 0));
        if (count($matches) !== 1) {
            throw new ProbeInputInvalid('Device selection is missing or ambiguous; use its listed index.');
        }

        return $matches[0];
    }
}
