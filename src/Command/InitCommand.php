<?php

declare(strict_types=1);

namespace Setono\DependencyTracker\Command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class InitCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('dependency-tracker:init');
        $this->setDescription('Scaffold a dependency-tracker.php config file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = dirname($this->requireComposer()->getConfig()->get('vendor-dir'));
        $configPath = $projectRoot . '/dependency-tracker.php';

        if (file_exists($configPath)) {
            $output->writeln('<info>dependency-tracker.php already exists, nothing to do.</info>');

            return 0;
        }

        file_put_contents($configPath, <<<'PHP'
            <?php

            declare(strict_types=1);

            use Setono\DependencyTracker\Config;

            return static function (Config $config): void {
                // Track a directory. All files in it (recursively) will be snapshotted.
                // $config->track('vendor/vendor-name/package-name/path/to/directory');

                // Track a directory with a file filter and non-recursive traversal.
                // $config->track('vendor/vendor-name/package-name/path/to/directory')
                //     ->filter('*.php')
                //     ->recursive(false);

                // Track a single file.
                // $config->track('vendor/vendor-name/package-name/path/to/File.php');

                // Change the output directory (default: .dependency-snapshots).
                // $config->setOutputDir('.dependency-snapshots');
            };

            PHP);

        $output->writeln('<info>Created dependency-tracker.php — open it and add your tracked paths.</info>');
        $output->writeln('Run "composer install" or "composer update" to trigger the first snapshot.');

        return 0;
    }
}
