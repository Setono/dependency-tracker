<?php

declare(strict_types=1);

namespace Setono\DependencyTracker\Tests;

use PHPUnit\Framework\TestCase;
use Setono\DependencyTracker\Config;
use Setono\DependencyTracker\Track;

final class ConfigTest extends TestCase
{
    public function testDefaultOutputDir(): void
    {
        $config = new Config();

        self::assertSame('.dependency-snapshots', $config->getOutputDir());
    }

    public function testSetOutputDir(): void
    {
        $config = new Config();
        $config->setOutputDir('.custom-snapshots');

        self::assertSame('.custom-snapshots', $config->getOutputDir());
    }

    public function testSetOutputDirReturnsSelfForChaining(): void
    {
        $config = new Config();
        $result = $config->setOutputDir('.custom-snapshots');

        self::assertSame($config, $result);
    }

    public function testTrackReturnsTrackInstance(): void
    {
        $config = new Config();
        $track = $config->track('vendor/acme/package/src');

        self::assertInstanceOf(Track::class, $track);
    }

    public function testTrackRegistersPath(): void
    {
        $config = new Config();
        $config->track('vendor/acme/package/src');

        $tracks = $config->getTracks();
        self::assertCount(1, $tracks);
        self::assertSame('vendor/acme/package/src', $tracks[0]->getPath());
    }

    public function testMultipleTracks(): void
    {
        $config = new Config();
        $config->track('vendor/acme/package/src');
        $config->track('vendor/other/package/config');
        $config->track('vendor/third/package/File.php');

        $tracks = $config->getTracks();
        self::assertCount(3, $tracks);
        self::assertSame('vendor/acme/package/src', $tracks[0]->getPath());
        self::assertSame('vendor/other/package/config', $tracks[1]->getPath());
        self::assertSame('vendor/third/package/File.php', $tracks[2]->getPath());
    }

    public function testEmptyTracksByDefault(): void
    {
        $config = new Config();

        self::assertSame([], $config->getTracks());
    }

    public function testTrackReturnsSameObjectAsRegistered(): void
    {
        $config = new Config();
        $track = $config->track('vendor/acme/package/src');
        $track->filter('*.php')->recursive(false);

        $registered = $config->getTracks()[0];
        self::assertSame($track, $registered);
        self::assertSame(['*.php'], $registered->getFilters());
        self::assertFalse($registered->isRecursive());
    }
}
