# Dependency Tracker

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]][link-license]
[![Build Status][ico-build]][link-build]
[![Code Coverage][ico-code-coverage]][link-code-coverage]
[![Mutation testing badge][ico-mutation]][link-mutation]

A Composer plugin that snapshots tracked files and directories from `vendor/` into a committed project directory. After each `composer install` or `composer update`, any changes in tracked paths become visible via `git diff`.

## Installation

```bash
composer require setono/dependency-tracker
```

## Quick start

Scaffold a config file:

```bash
composer dependency-tracker:init
```

This creates `dependency-tracker.php` in your project root. Open it and add the vendor paths you want to track:

```php
<?php

declare(strict_types=1);

use Setono\DependencyTracker\Config;

return static function (Config $config): void {
    // Track a full directory recursively
    $config->track('vendor/sylius/sylius/src/Sylius/Bundle/CoreBundle/Migrations');

    // Track a directory but only *.php files, non-recursively
    $config->track('vendor/acme/package/config')
        ->filter('*.php')
        ->recursive(false);

    // Track a single file
    $config->track('vendor/doctrine/migrations/src/Version/MigrationStatusCalculator.php');

    // Change the output directory (default: .dependency-snapshots)
    $config->setOutputDir('.dependency-snapshots');
};
```

Run `composer install` or `composer update` to trigger the first snapshot. The tracked files are copied into `.dependency-snapshots/` where they are visible to git:

```bash
git diff
git status
```

## What to commit

1. `dependency-tracker.php` -- the config file
2. The entire `.dependency-snapshots/` directory -- the snapshots themselves

Do **not** add `.dependency-snapshots/` to `.gitignore`.

## How it works

On every `composer install` or `composer update` the plugin:

1. Reads `dependency-tracker.php` from the project root
2. For each tracked **directory**: deletes the snapshot copy entirely and re-copies all matching files. This means additions, modifications, and deletions in vendor are all visible in `git diff`.
3. For each tracked **file**: copies it to the mirrored location. If the source file was removed from vendor, the snapshot copy is deleted automatically.

### Filters

Filters are matched against filenames (not full paths) using `fnmatch()`. Multiple patterns use OR logic:

```php
$config->track('vendor/acme/package/src')
    ->filter('*.php', '*.xml'); // includes .php and .xml files
```

### Non-recursive mode

By default directories are traversed recursively. To track only the files directly inside a directory:

```php
$config->track('vendor/acme/package/config')
    ->recursive(false);
```

## Configuration reference

### `Config` methods

| Method | Returns | Description |
|---|---|---|
| `track(string $path)` | `Track` | Register a path relative to project root |
| `setOutputDir(string $dir)` | `self` | Set output directory (default: `.dependency-snapshots`) |

### `Track` methods

| Method | Returns | Default | Description |
|---|---|---|---|
| `filter(string ...$patterns)` | `self` | No filter (all files) | Glob patterns matched against filenames |
| `recursive(bool $recursive)` | `self` | `true` | Whether to descend into subdirectories |

## When a tracked path is missing

If a tracked path does not exist in `vendor/` at run time, the plugin emits a warning and continues processing the remaining tracks. This is not fatal.

## Uninstalling

```bash
composer remove setono/dependency-tracker
```

Then delete `dependency-tracker.php` and `.dependency-snapshots/` from your project.

[ico-version]: https://poser.pugx.org/setono/dependency-tracker/v/stable
[ico-license]: https://poser.pugx.org/setono/dependency-tracker/license
[ico-build]: https://github.com/Setono/dependency-tracker/actions/workflows/build.yaml/badge.svg?branch=master
[ico-code-coverage]: https://codecov.io/gh/Setono/dependency-tracker/graph/badge.svg
[ico-mutation]: https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2Fdependency-tracker%2Fmaster

[link-packagist]: https://packagist.org/packages/setono/dependency-tracker
[link-license]: LICENSE
[link-build]: https://github.com/Setono/dependency-tracker/actions/workflows/build.yaml?query=branch%3Amaster
[link-code-coverage]: https://codecov.io/gh/Setono/dependency-tracker
[link-mutation]: https://dashboard.stryker-mutator.io/reports/github.com/Setono/dependency-tracker/master
