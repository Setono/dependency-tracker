<?php

declare(strict_types=1);

namespace Setono\DependencyTracker\Tests;

use Composer\Composer;
use Composer\Config as ComposerConfig;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Script\ScriptEvents;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\DependencyTracker\Command\CommandProvider as PluginCommandProvider;
use Setono\DependencyTracker\Plugin;

final class PluginTest extends TestCase
{
    use ProphecyTrait;

    private string $projectRoot;

    /** @var ObjectProphecy<Composer> */
    private ObjectProphecy $composer;

    /** @var ObjectProphecy<IOInterface> */
    private ObjectProphecy $io;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/dependency-tracker-plugin-test-' . uniqid();
        mkdir($this->projectRoot, 0777, true);
        mkdir($this->projectRoot . '/vendor', 0777, true);

        $composerConfig = $this->prophesize(ComposerConfig::class);
        $composerConfig->get('vendor-dir')->willReturn($this->projectRoot . '/vendor');

        $this->composer = $this->prophesize(Composer::class);
        $this->composer->getConfig()->willReturn($composerConfig->reveal());

        $this->io = $this->prophesize(IOInterface::class);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->projectRoot);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = Plugin::getSubscribedEvents();

        self::assertArrayHasKey(ScriptEvents::POST_INSTALL_CMD, $events);
        self::assertArrayHasKey(ScriptEvents::POST_UPDATE_CMD, $events);
        self::assertSame('onPostInstallOrUpdate', $events[ScriptEvents::POST_INSTALL_CMD]);
        self::assertSame('onPostInstallOrUpdate', $events[ScriptEvents::POST_UPDATE_CMD]);
    }

    public function testGetCapabilities(): void
    {
        $plugin = new Plugin();
        $capabilities = $plugin->getCapabilities();

        self::assertArrayHasKey(CommandProvider::class, $capabilities);
        self::assertSame(PluginCommandProvider::class, $capabilities[CommandProvider::class]);
    }

    public function testOnPostInstallOrUpdateSilentlyReturnsWhenConfigFileMissing(): void
    {
        $plugin = new Plugin();
        $plugin->activate($this->composer->reveal(), $this->io->reveal());

        $plugin->onPostInstallOrUpdate();

        $this->io->write(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->io->writeError(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testOnPostInstallOrUpdateErrorsWhenConfigFileNotCallable(): void
    {
        file_put_contents(
            $this->projectRoot . '/dependency-tracker.php',
            '<?php return "not a callable";',
        );

        $plugin = new Plugin();
        $plugin->activate($this->composer->reveal(), $this->io->reveal());

        $plugin->onPostInstallOrUpdate();

        $this->io->writeError(Argument::containingString('must return a callable'))
            ->shouldHaveBeenCalledOnce();
    }

    public function testOnPostInstallOrUpdateVerboseNoticeWhenNoTracks(): void
    {
        file_put_contents(
            $this->projectRoot . '/dependency-tracker.php',
            '<?php use Setono\DependencyTracker\Config; return static function (Config $config): void {};',
        );

        $plugin = new Plugin();
        $plugin->activate($this->composer->reveal(), $this->io->reveal());

        $plugin->onPostInstallOrUpdate();

        $this->io->write(
            Argument::containingString('No paths configured, skipping'),
            true,
            IOInterface::VERBOSE,
        )->shouldHaveBeenCalledOnce();
    }

    public function testOnPostInstallOrUpdateRunsTrackerWithValidConfig(): void
    {
        // Create a source file to track
        $sourceDir = $this->projectRoot . '/vendor/acme/package';
        mkdir($sourceDir, 0777, true);
        file_put_contents($sourceDir . '/File.php', '<?php // test');

        file_put_contents(
            $this->projectRoot . '/dependency-tracker.php',
            '<?php use Setono\DependencyTracker\Config; return static function (Config $config): void { $config->track("vendor/acme/package/File.php"); };',
        );

        $plugin = new Plugin();
        $plugin->activate($this->composer->reveal(), $this->io->reveal());

        $plugin->onPostInstallOrUpdate();

        self::assertFileExists(
            $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/File.php',
        );
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
