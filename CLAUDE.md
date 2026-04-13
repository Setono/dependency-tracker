# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all tests
vendor/bin/phpunit

# Run a single test class
vendor/bin/phpunit tests/TrackerTest.php

# Run a single test method
vendor/bin/phpunit --filter testSyncDirectoryRecursive

# Validate composer.json
composer validate --strict
```

## Architecture

This is a Composer plugin (`composer-plugin` type) that snapshots tracked files from `vendor/` into a committed `.dependency-snapshots/` directory so that vendor changes are visible via `git diff`.

**Plugin lifecycle**: `Plugin` subscribes to Composer's `POST_INSTALL_CMD` and `POST_UPDATE_CMD` events. On each event it loads the user's `dependency-tracker.php` config file (a closure receiving a `Config` object), then passes the resolved config to `Tracker::run()`.

**Config flow**: `Config` holds a list of `Track` value objects (each representing a path + optional glob filters + recursive flag) and an output directory. `Track` is returned from `Config::track()` for fluent per-path configuration.

**Sync strategy**: `Tracker` deletes-and-recopies entire snapshot directories on each run. This ensures additions, modifications, and deletions in vendor all appear in `git diff`. Single-file tracks are copied in-place with orphan cleanup.

**Commands**: `InitCommand` (registered via `CommandProvider` + the `Capable` interface) scaffolds a starter `dependency-tracker.php` config file.

**Error handling**: The plugin never throws — all errors go through `$io->writeError()` to avoid aborting `composer install/update`.

## Testing conventions

Use **Prophecy** (`phpspec/prophecy-phpunit`) for all test doubles — not PHPUnit's built-in mocking. Tests use `ProphecyTrait` and `$this->prophesize()` for stubs/mocks.
