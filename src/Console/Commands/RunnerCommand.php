<?php

namespace Obelaw\Runner\Console\Commands;

use Illuminate\Console\Command;
use Obelaw\Runner\RunnerPool;
use Obelaw\Runner\Services\RunnerService;

class RunnerCommand extends Command
{
    /**
     * Signature with rich filtering & execution controls.
     */
    protected $signature = 'runner:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obelaw Runner Commands';

    public function handle(): void
    {
        $runnerService = new RunnerService(RunnerPool::getPaths());
        $runnerService->run();
    }
}
