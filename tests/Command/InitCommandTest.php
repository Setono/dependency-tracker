<?php

declare(strict_types=1);

namespace Setono\DependencyTracker\Tests\Command;

use Composer\Composer;
use Composer\Config as ComposerConfig;
use Composer\EventDispatcher\EventDispatcher;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\DependencyTracker\Command\InitCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class InitCommandTest extends TestCase
{
    use ProphecyTrait;

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
        $composerConfig = $this->prophesize(ComposerConfig::class);
        $composerConfig->get('vendor-dir')->willReturn($this->projectRoot . '/vendor');

        $eventDispatcher = $this->prophesize(EventDispatcher::class);
        $eventDispatcher->dispatch(Argument::cetera())->willReturn(0);

        $composer = $this->prophesize(Composer::class);
        $composer->getConfig()->willReturn($composerConfig->reveal());
        $composer->getEventDispatcher()->willReturn($eventDispatcher->reveal());

        $command = new InitCommand();
        $command->setComposer($composer->reveal());

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
