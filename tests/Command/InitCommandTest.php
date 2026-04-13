<?php

declare(strict_types=1);

namespace Setono\DependencyTracker\Tests\Command;

use Composer\Composer;
use Composer\Config as ComposerConfig;
use PHPUnit\Framework\TestCase;
use Setono\DependencyTracker\Command\InitCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class InitCommandTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/dependency-tracker-init-test-' . uniqid();
        mkdir($this->projectRoot, 0777, true);
        mkdir($this->projectRoot . '/vendor', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->projectRoot);
    }

    public function testCreatesConfigFileWhenNoneExists(): void
    {
        $command = $this->createCommand();
        $output = new BufferedOutput();

        $exitCode = $command->run(new ArrayInput([]), $output);

        self::assertSame(0, $exitCode);
        self::assertFileExists($this->projectRoot . '/dependency-tracker.php');
    }

    public function testStubContentMatchesExpectedTemplate(): void
    {
        $command = $this->createCommand();
        $output = new BufferedOutput();

        $command->run(new ArrayInput([]), $output);

        $content = file_get_contents($this->projectRoot . '/dependency-tracker.php');

        self::assertStringContainsString('<?php', $content);
        self::assertStringContainsString('declare(strict_types=1)', $content);
        self::assertStringContainsString('use Setono\DependencyTracker\Config', $content);
        self::assertStringContainsString('return static function (Config $config): void {', $content);
        self::assertStringContainsString('$config->track(', $content);
        self::assertStringContainsString('->filter(\'*.php\')', $content);
        self::assertStringContainsString('->recursive(false)', $content);
        self::assertStringContainsString('$config->setOutputDir(\'.dependency-snapshots\')', $content);
    }

    public function testOutputMessagesOnCreation(): void
    {
        $command = $this->createCommand();
        $output = new BufferedOutput();

        $command->run(new ArrayInput([]), $output);

        $text = $output->fetch();
        self::assertStringContainsString('Created dependency-tracker.php', $text);
        self::assertStringContainsString('open it and add your tracked paths', $text);
        self::assertStringContainsString('composer install', $text);
    }

    public function testSkipsCreationWhenFileAlreadyExists(): void
    {
        file_put_contents($this->projectRoot . '/dependency-tracker.php', 'existing content');

        $command = $this->createCommand();
        $output = new BufferedOutput();

        $exitCode = $command->run(new ArrayInput([]), $output);

        self::assertSame(0, $exitCode);
        self::assertSame('existing content', file_get_contents($this->projectRoot . '/dependency-tracker.php'));

        $text = $output->fetch();
        self::assertStringContainsString('already exists', $text);
    }

    private function createCommand(): InitCommand
    {
        $composerConfig = $this->createMock(ComposerConfig::class);
        $composerConfig->method('get')
            ->with('vendor-dir')
            ->willReturn($this->projectRoot . '/vendor');

        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')->willReturn($composerConfig);

        $command = new InitCommand();
        $command->setComposer($composer);

        return $command;
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
