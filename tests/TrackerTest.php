<?php

declare(strict_types=1);

namespace Setono\DependencyTracker\Tests;

use Composer\IO\IOInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\DependencyTracker\Config;
use Setono\DependencyTracker\Tracker;

final class TrackerTest extends TestCase
{
    use ProphecyTrait;

    private string $projectRoot;

    /** @var ObjectProphecy<IOInterface> */
    private ObjectProphecy $io;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/dependency-tracker-test-' . uniqid();
        mkdir($this->projectRoot, 0777, true);

        $this->io = $this->prophesize(IOInterface::class);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->projectRoot);
    }

    // --- run() tests ---

    public function testRunCreatesSnapshotDirectory(): void
    {
        $config = new Config();
        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);

        $tracker->run();

        self::assertDirectoryExists($this->projectRoot . '/.dependency-snapshots');
    }

    public function testRunCreatesGitignore(): void
    {
        $config = new Config();
        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);

        $tracker->run();

        $gitignorePath = $this->projectRoot . '/.dependency-snapshots/.gitignore';
        self::assertFileExists($gitignorePath);

        $content = file_get_contents($gitignorePath);
        self::assertStringContainsString('managed by setono/dependency-tracker', $content);
        self::assertStringContainsString('Do NOT add ignore rules here', $content);
    }

    public function testRunDoesNotOverwriteExistingGitignore(): void
    {
        $snapshotDir = $this->projectRoot . '/.dependency-snapshots';
        mkdir($snapshotDir, 0777, true);
        file_put_contents($snapshotDir . '/.gitignore', 'custom content');

        $config = new Config();
        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);

        $tracker->run();

        self::assertSame('custom content', file_get_contents($snapshotDir . '/.gitignore'));
    }

    public function testRunUsesCustomOutputDir(): void
    {
        $config = new Config();
        $config->setOutputDir('.custom-snapshots');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        self::assertDirectoryExists($this->projectRoot . '/.custom-snapshots');
    }

    public function testRunEmitsWarningForNonExistentPath(): void
    {
        $config = new Config();
        $config->track('vendor/nonexistent/path');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        $this->io->writeError(Argument::containingString('Tracked path does not exist, skipping: vendor/nonexistent/path'))
            ->shouldHaveBeenCalledOnce();
    }

    public function testRunContinuesAfterNonExistentPath(): void
    {
        $this->createSourceFile('vendor/existing/file.txt', 'hello');

        $config = new Config();
        $config->track('vendor/nonexistent/path');
        $config->track('vendor/existing/file.txt');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        self::assertFileExists($this->projectRoot . '/.dependency-snapshots/vendor/existing/file.txt');
    }

    public function testRunEmitsSummaryWithCorrectCount(): void
    {
        $this->createSourceFile('vendor/a/file.txt', 'a');
        $this->createSourceDir('vendor/b/dir', ['one.txt' => '1']);

        $config = new Config();
        $config->track('vendor/a/file.txt');
        $config->track('vendor/nonexistent/path');
        $config->track('vendor/b/dir');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        $this->io->write(Argument::containingString('Snapshotted 2 path(s)'))
            ->shouldHaveBeenCalled();
    }

    // --- Directory sync tests ---

    public function testSyncDirectoryRecursive(): void
    {
        $this->createSourceDir('vendor/acme/package/src', [
            'A.php' => '<?php class A {}',
            'B.php' => '<?php class B {}',
        ]);
        $this->createSourceDir('vendor/acme/package/src/Sub', [
            'C.php' => '<?php class C {}',
        ]);

        $config = new Config();
        $config->track('vendor/acme/package/src');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        $snapshotBase = $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/src';
        self::assertFileExists($snapshotBase . '/A.php');
        self::assertFileExists($snapshotBase . '/B.php');
        self::assertFileExists($snapshotBase . '/Sub/C.php');
        self::assertSame('<?php class A {}', file_get_contents($snapshotBase . '/A.php'));
    }

    public function testSyncDirectoryDeletesExistingDestination(): void
    {
        $this->createSourceDir('vendor/acme/package/src', [
            'A.php' => 'version1',
        ]);

        $config = new Config();
        $config->track('vendor/acme/package/src');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        $snapshotBase = $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/src';
        self::assertSame('version1', file_get_contents($snapshotBase . '/A.php'));

        // Now change the source and add a new file, remove old one
        unlink($this->projectRoot . '/vendor/acme/package/src/A.php');
        file_put_contents($this->projectRoot . '/vendor/acme/package/src/B.php', 'new file');

        $tracker->run();

        self::assertFileDoesNotExist($snapshotBase . '/A.php');
        self::assertFileExists($snapshotBase . '/B.php');
    }

    public function testSyncDirectoryNonRecursive(): void
    {
        $this->createSourceDir('vendor/acme/package/config', [
            'services.php' => 'services',
            'routes.php' => 'routes',
        ]);
        $this->createSourceDir('vendor/acme/package/config/sub', [
            'nested.php' => 'nested',
        ]);

        $config = new Config();
        $config->track('vendor/acme/package/config')->recursive(false);

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        $snapshotBase = $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/config';
        self::assertFileExists($snapshotBase . '/services.php');
        self::assertFileExists($snapshotBase . '/routes.php');
        self::assertFileDoesNotExist($snapshotBase . '/sub/nested.php');
    }

    public function testSyncDirectoryWithSingleFilter(): void
    {
        $this->createSourceDir('vendor/acme/package/src', [
            'A.php' => 'php',
            'B.xml' => 'xml',
            'C.php' => 'php',
        ]);

        $config = new Config();
        $config->track('vendor/acme/package/src')->filter('*.php');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        $snapshotBase = $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/src';
        self::assertFileExists($snapshotBase . '/A.php');
        self::assertFileExists($snapshotBase . '/C.php');
        self::assertFileDoesNotExist($snapshotBase . '/B.xml');
    }

    public function testSyncDirectoryWithMultipleFilters(): void
    {
        $this->createSourceDir('vendor/acme/package/src', [
            'A.php' => 'php',
            'B.xml' => 'xml',
            'C.yaml' => 'yaml',
        ]);

        $config = new Config();
        $config->track('vendor/acme/package/src')->filter('*.php', '*.xml');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        $snapshotBase = $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/src';
        self::assertFileExists($snapshotBase . '/A.php');
        self::assertFileExists($snapshotBase . '/B.xml');
        self::assertFileDoesNotExist($snapshotBase . '/C.yaml');
    }

    public function testSyncDirectoryWithFilterNoMatches(): void
    {
        $this->createSourceDir('vendor/acme/package/src', [
            'A.php' => 'php',
        ]);

        $config = new Config();
        $config->track('vendor/acme/package/src')->filter('*.json');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        $snapshotBase = $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/src';
        // Directory exists (it's always created) but no files inside
        self::assertDirectoryExists($snapshotBase);
        self::assertFileDoesNotExist($snapshotBase . '/A.php');
    }

    public function testSyncDirectoryPreservesNestedStructure(): void
    {
        $this->createSourceDir('vendor/acme/package/src/A/B/C', [
            'Deep.php' => 'deep',
        ]);

        $config = new Config();
        $config->track('vendor/acme/package/src');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        $snapshotBase = $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/src';
        self::assertFileExists($snapshotBase . '/A/B/C/Deep.php');
        self::assertSame('deep', file_get_contents($snapshotBase . '/A/B/C/Deep.php'));
    }

    public function testSyncDirectoryWithFilterAndRecursive(): void
    {
        $this->createSourceDir('vendor/acme/package/src', [
            'A.php' => 'php',
            'B.xml' => 'xml',
        ]);
        $this->createSourceDir('vendor/acme/package/src/Sub', [
            'C.php' => 'php-sub',
            'D.txt' => 'txt-sub',
        ]);

        $config = new Config();
        $config->track('vendor/acme/package/src')->filter('*.php');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        $snapshotBase = $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/src';
        self::assertFileExists($snapshotBase . '/A.php');
        self::assertFileDoesNotExist($snapshotBase . '/B.xml');
        self::assertFileExists($snapshotBase . '/Sub/C.php');
        self::assertFileDoesNotExist($snapshotBase . '/Sub/D.txt');
    }

    // --- File sync tests ---

    public function testSyncFile(): void
    {
        $this->createSourceFile('vendor/acme/package/File.php', '<?php echo "hello";');

        $config = new Config();
        $config->track('vendor/acme/package/File.php');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        $dest = $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/File.php';
        self::assertFileExists($dest);
        self::assertSame('<?php echo "hello";', file_get_contents($dest));
    }

    public function testSyncFileOverwritesExisting(): void
    {
        $this->createSourceFile('vendor/acme/package/File.php', 'version1');

        $config = new Config();
        $config->track('vendor/acme/package/File.php');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        // Change source
        file_put_contents($this->projectRoot . '/vendor/acme/package/File.php', 'version2');

        $tracker->run();

        $dest = $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/File.php';
        self::assertSame('version2', file_get_contents($dest));
    }

    public function testSyncFileCreatesParentDirectories(): void
    {
        $this->createSourceFile('vendor/acme/package/deep/nested/File.php', 'content');

        $config = new Config();
        $config->track('vendor/acme/package/deep/nested/File.php');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        self::assertFileExists(
            $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/deep/nested/File.php',
        );
    }

    // --- Path with leading slash ---

    public function testTrackPathWithLeadingSlashIsHandled(): void
    {
        $this->createSourceFile('vendor/acme/package/File.php', 'content');

        $config = new Config();
        $config->track('/vendor/acme/package/File.php');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        self::assertFileExists(
            $this->projectRoot . '/.dependency-snapshots/vendor/acme/package/File.php',
        );
    }

    // --- Mixed tracks ---

    public function testMixedFileAndDirectoryTracks(): void
    {
        $this->createSourceFile('vendor/acme/package/single.php', 'single');
        $this->createSourceDir('vendor/acme/package/dir', [
            'A.php' => 'dir-a',
        ]);

        $config = new Config();
        $config->track('vendor/acme/package/single.php');
        $config->track('vendor/acme/package/dir');

        $tracker = new Tracker($this->io->reveal(), $this->projectRoot, $config);
        $tracker->run();

        $snapshot = $this->projectRoot . '/.dependency-snapshots';
        self::assertFileExists($snapshot . '/vendor/acme/package/single.php');
        self::assertFileExists($snapshot . '/vendor/acme/package/dir/A.php');
    }

    // --- Helpers ---

    private function createSourceFile(string $relativePath, string $content): void
    {
        $fullPath = $this->projectRoot . '/' . $relativePath;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $content);
    }

    /**
     * @param array<string, string> $files
     */
    private function createSourceDir(string $relativePath, array $files): void
    {
        $fullPath = $this->projectRoot . '/' . $relativePath;

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        foreach ($files as $name => $content) {
            file_put_contents($fullPath . '/' . $name, $content);
        }
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
