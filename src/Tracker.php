<?php

declare(strict_types=1);

namespace Setono\DependencyTracker;

use Composer\IO\IOInterface;
use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class Tracker
{
    public function __construct(
        private readonly IOInterface $io,
        private readonly string $projectRoot,
        private readonly Config $config,
    ) {
    }

    public function run(): void
    {
        $snapshotRoot = $this->projectRoot . '/' . $this->config->getOutputDir();

        if (!is_dir($snapshotRoot)) {
            mkdir($snapshotRoot, 0777, true);
        }

        $gitignorePath = $snapshotRoot . '/.gitignore';
        if (!file_exists($gitignorePath)) {
            file_put_contents($gitignorePath, <<<'GITIGNORE'
                # This directory is managed by setono/dependency-tracker.
                # Do NOT add ignore rules here. All files in this directory must be committed
                # so that vendor changes are visible in git diff.
                GITIGNORE);
        }

        $syncedCount = 0;

        foreach ($this->config->getTracks() as $track) {
            $sourcePath = $this->projectRoot . '/' . ltrim($track->getPath(), '/');
            $destPath = $snapshotRoot . '/' . ltrim($track->getPath(), '/');

            if (!file_exists($sourcePath)) {
                $this->io->writeError(sprintf(
                    '<warning>[DependencyTracker] Tracked path does not exist, skipping: %s</warning>',
                    $track->getPath(),
                ));

                continue;
            }

            if (is_dir($sourcePath)) {
                $this->syncDirectory($track, $sourcePath, $destPath);
            } else {
                $this->syncFile($sourcePath, $destPath);
            }

            ++$syncedCount;
        }

        $this->io->write(sprintf(
            '<info>[DependencyTracker] Snapshotted %d path(s) into "%s"</info>',
            $syncedCount,
            $this->config->getOutputDir(),
        ));
    }

    private function syncDirectory(Track $track, string $source, string $dest): void
    {
        if (is_dir($dest)) {
            $this->deleteDirectory($dest);
        }

        mkdir($dest, 0777, true);

        if ($track->isRecursive()) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );
        } else {
            $iterator = new DirectoryIterator($source);
        }

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filename = $file->getFilename();

            if (!$this->matchesFilters($filename, $track->getFilters())) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($source));
            $destFile = $dest . $relativePath;
            $destDir = dirname($destFile);

            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }

            copy($file->getPathname(), $destFile);
        }

        $this->io->write(
            sprintf('[DependencyTracker] Synced directory: %s', $track->getPath()),
            true,
            IOInterface::VERBOSE,
        );
    }

    private function syncFile(string $source, string $dest): void
    {
        if (!file_exists($source)) {
            if (file_exists($dest)) {
                unlink($dest);
                $this->io->write(
                    sprintf('[DependencyTracker] Removed orphaned snapshot: %s', $dest),
                    true,
                    IOInterface::VERBOSE,
                );
            }

            return;
        }

        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }

        copy($source, $dest);

        $this->io->write(
            sprintf('[DependencyTracker] Synced file: %s', $source),
            true,
            IOInterface::VERBOSE,
        );
    }

    /**
     * @param list<string> $filters
     */
    private function matchesFilters(string $filename, array $filters): bool
    {
        if ($filters === []) {
            return true;
        }

        foreach ($filters as $pattern) {
            if (fnmatch($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    private function deleteDirectory(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $file */
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
