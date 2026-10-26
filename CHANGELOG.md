# Changelog

All notable changes will be documented here, following Keep a Changelog and
Semantic Versioning.

## [Unreleased]

### Added

- Repository scaffolding, legal notices, bootstrap tests and release workflows.
- Core SDK integration with Laravel HTTP, cache, logging and clock services.
- Publishable, validated configuration with region presets and refresh disabled
  by default for the bundled transport.
- Scoped manager, auto-discovered provider and facade with independent accounts.
- Namespaced sessions, cooldowns, atomic owner-aware locks where supported and
  conservative best-effort leases otherwise.
- Laravel-native fakes, isolated fake sessions and wake/control assertions.
- Privacy-filtered diagnostic command with hidden/stdin credentials, explicit
  wake, bounded watch mode, raw attributes and inferred capabilities.
- Native HTTP, Redis ownership and dev-free archived-consumer integration tests
  for both supported Laravel majors.

### Changed

- Made package documentation self-contained.
- Allowed Pest 5 and its Laravel plugin on compatible PHP and Laravel versions.
- Documented the complete public integration and synthetic testing examples.
- Adopted maintainer-published GitHub releases with read-only validation instead
  of mandatory signed tags and automated publication.
