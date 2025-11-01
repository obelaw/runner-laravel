<?php

namespace Obelaw\Runner\Services;

class RunnerService
{
    public function __construct(private array $runnerPools) {}
    public function run(): void
    {
        foreach ($this->runnerPools as $path) {
            // Implementation for running the script at the given path
        }
    }
}
