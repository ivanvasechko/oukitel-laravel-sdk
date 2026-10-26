# Oukitel Laravel SDK (unofficial)

**Unofficial. Not affiliated with, endorsed by, or supported by Oukitel,
Wonderfree, Quectel or Acceleronix.**

[![CI](https://github.com/ivanvasechko/oukitel-laravel-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/ivanvasechko/oukitel-laravel-sdk/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-PolyForm%20Noncommercial-blue)](LICENSE)

Source-available Laravel integration for personal and other permitted
noncommercial uses under [PolyForm Noncommercial 1.0.0](LICENSE).
This is not an OSI open-source licence.

## Status

Laravel-native integration for
[oukitel-php-sdk](https://github.com/ivanvasechko/oukitel-php-sdk):
HTTP fakes, cache-backed sessions, owner-aware locks, a scoped manager and
facade, test fakes, and a privacy-filtered diagnostic command.
The core SDK owns the cloud protocol, parsing, authentication and retry policy.
This wrapper does not implement a second protocol.

The first functional release is pending. The examples below describe the
upcoming release, not an already published version. Vendor API changes can
break this unofficial integration without notice.

## Install and quick start

After the first release is published:

```sh
composer require ivanvasechko/oukitel-laravel-sdk
```

| Laravel | PHP | Core SDK |
| --- | --- | --- |
| 12 | 8.2-8.5 | ^0.3 |
| 13 | 8.3-8.5 | ^0.3 |

Laravel 11 is not supported. CI resolves lowest and latest supported dependencies
and runs Composer auditing; vulnerability status can change after publication.
Provider and facade discovery are automatic unless your application disables
package discovery. In that case register
`IvanVasechko\Oukitel\Laravel\OukitelServiceProvider` manually.

Use real credentials supplied securely by your application at runtime.
All credentials and identifiers shown here are synthetic:

```php
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Laravel\Facades\Oukitel;

$account = Oukitel::account(new Credentials(
    Region::Eu,
    'reader@example.test',
    'synthetic-password',
));
$devices = $account->devices();
if ($devices !== []) {
    $telemetry = $account->telemetry($devices[0]->id);
    $batteryPercent = $telemetry->batteryPercent; // Nullable when not reported.
}
```

Array credentials are also accepted. Region defaults to EU; string regions are
case-insensitive EU, US or CN:

```php
use IvanVasechko\Oukitel\Laravel\Facades\Oukitel;

$account = Oukitel::account([
    'region' => 'EU',
    'login' => 'reader@example.test',
    'password' => 'synthetic-password',
]);
$account->verify();
$cloud = Oukitel::cloud();
```

`verify()` forces a login check and returns no value. `devices()`,
`telemetry(DeviceId)`, `rawAttributes(DeviceId)` and `wake(DeviceId)` return
the core SDK's objects/results unchanged. Accounts are not memoized.
Inject `OukitelManager` when a facade is not appropriate.

`capabilities(DeviceId)` and `controls()` expose the core's **experimental**
API. Reported capabilities are not model safety guarantees. Controls can change
physical device state: consult the core README, validate model compatibility,
and never assume a successful request confirms a state change.
The diagnostic command does not send these controls.

## Configuration

```sh
php artisan vendor:publish --tag=oukitel-config
php artisan config:cache
```

The published [configuration](config/oukitel.php) is the complete reference.
Only that file reads environment values. Null, empty or whitespace-only region
overrides use the core's presets. Invalid settings raise a keyed
`Laravel\Exceptions\ConfigurationMissing` without including supplied values.

| Config key under `oukitel` | Default | Purpose |
| --- | --- | --- |
| `http.timeout_seconds` | 10 | Request deadline |
| `http.connect_timeout_seconds` | 3 | Connect deadline |
| `http.retry_times` | 3 | Maximum core read attempts |
| `http.retry_delay_milliseconds` | 250 | Delay between eligible read retries |
| `http.user_agent` | okhttp/4.9.3 | Protocol user agent |
| `session.refresh_before_seconds` | 120 | Access-session safety margin |
| `session.login_failure_cooldown_minutes` | 5 | Rejected-login cooldown |
| `session.use_refresh_token` | false | Requires a compatible custom cloud |
| `session.cache_store` | null | Laravel default cache store |
| `session.cache_prefix` | oukitel | Hashed cache namespace |
| `logging.channel` | null | Application's default PSR logger |
| `probe.login`, `probe.password` | null | Explicit opt-in probe credentials |

Region overrides use `regions.EU`, `regions.US` or `regions.CN`, with
`base_url`, `app_id`, `app_version`, `user_domain` and `user_domain_secret`.
Environment names follow `OUKITEL_EU_BASE_URL`, `OUKITEL_EU_APP_ID`, etc.
HTTP/session/logging/probe environment names are listed in the config file.

**Keep refresh disabled with the bundled transport.** That transport rejects
refresh-enabled options before any network request because refresh support is
not established. A consumer-provided `OukitelCloud` may implement refresh;
the core still owns refresh, cooldown, fallback and replay policy.

Redirects and HTTP-status exceptions are disabled in the Laravel adapter.
Eligible read retries occur only in the core. Wake/control requests are never
automatically retried. `Http::fake()` and `Http::preventStrayRequests()` work,
including when configured after the transport has been resolved.

### Cache, overrides and long-running workers

Sessions, cooldowns and lock keys are namespaced and hashed, without visible
account identifiers. Session values still contain tokens: use a trusted cache
backend and appropriate access controls. Hashing keys does not encrypt values.
Expired/malformed sessions follow the core's conservative handling;
cache and supported-backend lock errors are explicit failures.

Backends implementing Laravel's `LockProvider` use nonblocking owner-aware
locks. A stale owner cannot release a newer owner's lease. Use a shared backend
such as Redis for cross-worker coordination: array locks are process-local.
Backends without lock support use the core's **best-effort**, non-atomic lease;
this is not a distributed-lock guarantee. Errors do not trigger that fallback.

The default manager, client, cloud and session graph are scoped to Laravel's
request/job lifecycle. The facade does not retain a cached root across scope
resets. Neither the manager nor provider stores account credentials.
Reload persistent workers after changing configuration.

Consumer bindings for `OukitelCloud`, `SessionStore`, `ClockInterface` and
`OukitelClient` are honored; bind overrides before resolving the SDK graph.
Use scoped bindings for mutable custom services. Replacing a binding does not
retroactively rewrite accounts/clients that have already been returned.

## Testing

Use the package fake in PHPUnit/Pest tests before obtaining account handles:

```php
use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\Laravel\Facades\Oukitel;

$id = new DeviceId('synthetic-device-ABCD', 'synthetic-product');
$fake = Oukitel::fake()
    ->withDevices([new Device($id, 'Synthetic device', null, true)])
    ->withTelemetry($id, 'online');
$account = Oukitel::account([
    'login' => 'reader@example.test',
    'password' => 'synthetic-password',
]);
$telemetry = $account->telemetry($id);
$fake->assertNotWoke($id);
$account->wake($id);
$fake->assertWoke($id);
$fake->assertNoControlsSent();
```

Fixtures such as `online`, `offline` and `on-battery` are independently authored
synthetic data bundled in the core. You may instead pass a `Telemetry` object
and configure `withRawAttributes()` separately. `failLoginWith()`,
`failRefreshWith()` and `failControlWith()` accept exceptions for failure tests.
Unconfigured reads fail rather than silently inventing results.

`fake()` replaces subsequent facade/container resolutions and uses an isolated
array session store, never a previously cached real session. With the default
provider bindings the fake graph is cleared at the next Laravel scope reset.
Already returned handles are unchanged; custom binding lifetimes remain the
consumer's responsibility. Re-enabling a fake starts fresh recording/session state.
`assertNoControlsSent()` checks experimental controls, not wake requests.
The fake records attempts; it does not mutate telemetry or fabricate confirmation.

## Diagnostic command

List devices with an invisible password prompt:

```sh
php artisan oukitel:probe --region=EU --login=reader@example.test
```

Select a listed one-based index or unique four-character masked suffix:

```sh
php artisan oukitel:probe --login=reader@example.test --device=1
php artisan oukitel:probe --login=reader@example.test --device=ABCD --raw --capabilities
php artisan oukitel:probe --login=reader@example.test --device=1 --watch=5 --count=3
```

`--watch` requires a positive whole-second interval and selected device.
`--count` is a positive sample limit and requires watch mode. Omit count to
watch until interrupted. `--wake` explicitly requests one wake before sampling;
there is no implicit wake or heuristic. Selection must be unambiguous.

Automation must explicitly choose one input mode:

- `--password-stdin --login=...`: one password line.
- `--credentials-stdin`: login line, then password line.
- `--credentials-from-config`: use `probe.login`/`probe.password` explicitly.

Stdin input is newline-delimited; CRLF delimiters are accepted. Missing or empty
credentials and non-interactive prompts fail. Passwords are never CLI options.
Config credentials are never consumed implicitly; remember `config:cache`
serializes configured passwords, so prefer secure stdin for automation.

Output hides account login, free-form device/model names and full identifiers.
Only a masked device suffix is shown. Typed telemetry excludes raw/extra identity
fields. Raw output retains known codes and safe numeric/boolean values, filters
nested fields, and redacts unknown codes, names and text. It is intentionally not
a lossless response dump. Failures print generic diagnostics, not upstream bodies
or exception details. Do not paste credentials, raw SDK data or tokens into issues.

## Disclaimer and attribution

The integration uses a reverse-engineered API with no SLA, which may
change or break at any time. Using it may violate vendor terms of service;
you use it at your own risk and must obtain any necessary authorization.
All trademarks belong to their owners. The author claims no rights over vendor
APIs, constants or data formats. This licence covers only rights the author can grant.

Thanks to [VictorCV-DAM/ha-oukitel](https://github.com/VictorCV-DAM/ha-oukitel)
(GPL-3.0) for protocol research. No code from that project is included.
The wrapper delegates protocol operations to the independently implemented core.

## Rights-holder concerns

Contact me through a
[private report](https://github.com/ivanvasechko/oukitel-laravel-sdk/security/advisories/new)
and I will promptly change or remove disputed material, including bundled constants.

## Development

```sh
composer update
composer validate --strict
composer test
XDEBUG_MODE=coverage composer test-coverage
composer phpstan
composer pint -- --test
composer audit
php tests/Distribution/check.php 12
php tests/Distribution/check.php 13
```

This library intentionally does not commit `composer.lock`. Consumers use their
application lockfile; CI resolves both the lowest and latest supported dependency
versions. Run `composer update` to refresh the local development lockfile.

Tests boot Laravel with Orchestra Testbench. PHPStan checks production source
at maximum level. CI enforces 90% coverage, the complete lowest/latest framework
matrix, isolated Redis ownership and archived consumers without dev dependencies.
Live account/device access is not required or used by these checks.
Set `OUKITEL_TEST_REDIS=1` and `REDIS_HOST` only for an isolated Redis test service
when running the Redis ownership test locally.

## Releases

See [CONTRIBUTING.md](CONTRIBUTING.md). The maintainer publishes through GitHub's
Releases UI after exact-commit main CI succeeds; signing is optional.
The release workflow validates rather than publishes or deploys.
Packagist indexes Git tags separately; GitHub Deployments are not package releases.
Never move a published tag. No placeholder releases are published.

## Licence

Free to use and modify under [PolyForm Noncommercial 1.0.0](LICENSE).
Commercial use is not granted. See [NOTICE](NOTICE).
