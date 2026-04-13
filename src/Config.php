<?php

declare(strict_types=1);

namespace Setono\DependencyTracker;

final class Config
{
    /** @var list<Track> */
    private array $tracks = [];

    private string $outputDir = '.dependency-snapshots';

    public function track(string $path): Track
    {
        $track = new Track($path);
        $this->tracks[] = $track;

        return $track;
    }

    public function setOutputDir(string $dir): self
    {
        $this->outputDir = $dir;

        return $this;
    }

    /**
     * @return list<Track>
     */
    public function getTracks(): array
    {
        return $this->tracks;
    }

    public function getOutputDir(): string
    {
        return $this->outputDir;
    }
}
