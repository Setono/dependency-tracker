<?php

declare(strict_types=1);

namespace Setono\DependencyTracker;

final class Track
{
    /** @var list<string> */
    private array $filters = [];

    private bool $recursive = true;

    public function __construct(private readonly string $path)
    {
    }

    public function filter(string ...$patterns): self
    {
        foreach ($patterns as $pattern) {
            $this->filters[] = $pattern;
        }

        return $this;
    }

    public function recursive(bool $recursive): self
    {
        $this->recursive = $recursive;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return list<string>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function isRecursive(): bool
    {
        return $this->recursive;
    }
}
