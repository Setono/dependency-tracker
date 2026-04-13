<?php

declare(strict_types=1);

namespace Setono\DependencyTracker\Tests\Command;

use PHPUnit\Framework\TestCase;
use Setono\DependencyTracker\Command\CommandProvider;
use Setono\DependencyTracker\Command\InitCommand;

final class CommandProviderTest extends TestCase
{
    public function testGetCommandsReturnsInitCommand(): void
    {
        $provider = new CommandProvider();
        $commands = $provider->getCommands();

        self::assertCount(1, $commands);
        self::assertInstanceOf(InitCommand::class, $commands[0]);
    }
}
