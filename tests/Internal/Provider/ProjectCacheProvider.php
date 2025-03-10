<?php

namespace Psalm\Tests\Internal\Provider;

use Psalm\Internal\Provider\ProjectCacheProvider as PsalmProjectCacheProvider;

class ProjectCacheProvider extends PsalmProjectCacheProvider
{
    /**
     * @var int
     */
    private $last_run = 0;

    public function __construct()
    {
    }

    public function getLastRun(string $psalm_version): int
    {
        return $this->last_run;
    }

    public function processSuccessfulRun(float $start_time, string $psalm_version): void
    {
        $this->last_run = (int) $start_time;
    }

    public function canDiffFiles(): bool
    {
        return $this->last_run > 0;
    }

    public function hasLockfileChanged(): bool
    {
        return false;
    }

    public function updateComposerLockHash(): void
    {
    }
}
