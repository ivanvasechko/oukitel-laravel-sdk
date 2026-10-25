# Oukitel Laravel SDK (unofficial)

**Unofficial. Not affiliated with, endorsed by, or supported by Oukitel,
Wonderfree, Quectel or Acceleronix.**

[![CI](https://github.com/ivanvasechko/oukitel-laravel-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/ivanvasechko/oukitel-laravel-sdk/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-PolyForm%20Noncommercial-blue)](LICENSE)

Source-available Laravel integration for personal and other permitted
noncommercial uses under [PolyForm Noncommercial 1.0.0](LICENSE).
This is not an OSI open-source licence.

## Status

Repository bootstrap only. No provider, facade, protocol implementation, released
version or Packagist installation exists yet. Do not use in production.
Runtime integration follows in power-platform task 15-05.

## Install and quick start

Once released, installation will be:

```sh
composer require ivanvasechko/oukitel-laravel-sdk
```

No working SDK quick start exists yet. Planned support covers Laravel 11 on
PHP 8.2-8.4, Laravel 12 on PHP 8.2-8.5 and Laravel 13 on PHP 8.3-8.5.
Never paste credentials into issues, logs or examples.

## Disclaimer and attribution

The intended integration uses a reverse-engineered API with no SLA, which may
change or break at any time. Using it may violate vendor terms of service;
you use it at your own risk and must obtain any necessary authorization.
All trademarks belong to their owners. The author claims no rights over vendor
APIs, constants or data formats. This licence covers only rights the author can grant.

Thanks to [VictorCV-DAM/ha-oukitel](https://github.com/VictorCV-DAM/ha-oukitel)
(GPL-3.0) for protocol research. No code from that project is included.
The planned implementation follows the clean-room power-platform design.

## Rights-holder concerns

Contact me through a
[private report](https://github.com/ivanvasechko/oukitel-laravel-sdk/security/advisories/new)
and I will promptly change or remove disputed material, including bundled constants.

## Development

```sh
composer update
composer validate --strict
composer test
composer phpstan
composer pint -- --test
composer audit
```

Tests boot Laravel with Orchestra Testbench. PHPStan checks the bootstrap harness
only. CI enforces 90% coverage once production PHP source is added.
The core SDK dependency and discovery metadata are deliberately deferred until
the provider exists; this scaffold must not advertise nonexistent classes.

## Releases

See [CONTRIBUTING.md](CONTRIBUTING.md). No placeholder releases are published.
Packagist registration requires the maintainer's Packagist account.

## Licence

Free to use and modify under [PolyForm Noncommercial 1.0.0](LICENSE).
Commercial use is not granted. See [NOTICE](NOTICE).
