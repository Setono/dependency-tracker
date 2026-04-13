<?php

declare(strict_types=1);

namespace Setono\DependencyTracker;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

final class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{
    private Composer $composer;

    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstallOrUpdate',
            ScriptEvents::POST_UPDATE_CMD => 'onPostInstallOrUpdate',
        ];
    }

    public function getCapabilities(): array
    {
        return [
            \Composer\Plugin\Capability\CommandProvider::class => Command\CommandProvider::class,
        ];
    }

    public function onPostInstallOrUpdate(): void
    {
        /** @var string $vendorDir */
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDir);
        $configFilePath = $projectRoot . '/dependency-tracker.php';

        if (!file_exists($configFilePath)) {
            return;
        }

        $closure = require $configFilePath;

        if (!is_callable($closure)) {
            $this->io->writeError('<error>[DependencyTracker] dependency-tracker.php must return a callable.</error>');

            return;
        }

        $config = new Config();
        $closure($config);

        if ($config->getTracks() === []) {
            $this->io->write('[DependencyTracker] No paths configured, skipping.', true, IOInterface::VERBOSE);

            return;
        }

        $tracker = new Tracker($this->io, $projectRoot, $config);
        $tracker->run();
    }
}
