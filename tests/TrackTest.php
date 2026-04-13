<?php

declare(strict_types=1);

namespace Setono\DependencyTracker\Tests;

use PHPUnit\Framework\TestCase;
use Setono\DependencyTracker\Track;

final class TrackTest extends TestCase
{
    public function testDefaults(): void
    {
        $track = new Track('vendor/acme/package/src');

        self::assertSame('vendor/acme/package/src', $track->getPath());
        self::assertSame([], $track->getFilters());
        self::assertTrue($track->isRecursive());
    }

    public function testFilterAccumulatesPatterns(): void
    {
        $track = new Track('vendor/acme/package/src');
        $track->filter('*.php');
        $track->filter('*.xml');

        self::assertSame(['*.php', '*.xml'], $track->getFilters());
    }

    public function testFilterWithVariadicArgs(): void
    {
        $track = new Track('vendor/acme/package/src');
        $track->filter('*.php', '*.xml', '*.yaml');

        self::assertSame(['*.php', '*.xml', '*.yaml'], $track->getFilters());
    }

    public function testRecursiveCanBeDisabled(): void
    {
        $track = new Track('vendor/acme/package/src');
        $track->recursive(false);

        self::assertFalse($track->isRecursive());
    }

    public function testRecursiveCanBeReEnabled(): void
    {
        $track = new Track('vendor/acme/package/src');
        $track->recursive(false);
        $track->recursive(true);

        self::assertTrue($track->isRecursive());
    }

    public function testFilterReturnsSelfForChaining(): void
    {
        $track = new Track('vendor/acme/package/src');
        $result = $track->filter('*.php');

        self::assertSame($track, $result);
    }

    public function testRecursiveReturnsSelfForChaining(): void
    {
        $track = new Track('vendor/acme/package/src');
        $result = $track->recursive(false);

        self::assertSame($track, $result);
    }

    public function testFluentChaining(): void
    {
        $track = new Track('vendor/acme/package/config');
        $result = $track->filter('*.php')->recursive(false);

        self::assertSame($track, $result);
        self::assertSame(['*.php'], $track->getFilters());
        self::assertFalse($track->isRecursive());
    }

    public function testPathWithLeadingSlash(): void
    {
        $track = new Track('/vendor/acme/package/src');

        self::assertSame('/vendor/acme/package/src', $track->getPath());
    }
}
