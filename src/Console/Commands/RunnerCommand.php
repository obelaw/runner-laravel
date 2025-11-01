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
    protected $signature = 'runner:run {--tag= : Filter runners by tag}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute runners from configured pools';

    public function handle(): void
    {
        $tag = $this->option('tag');
        
        if ($tag) {
            $this->info("Running runners with tag: {$tag}");
        } else {
            $this->info("Running all runners...");
        }

        $runnerService = new RunnerService(RunnerPool::getPaths());
        $summary = $runnerService->run($tag);

        $this->displaySummary($summary, $tag);
    }

    /**
     * Display execution summary.
     *
     * @param array $summary
     * @param string|null $tag
     */
    private function displaySummary(array $summary, ?string $tag = null): void
    {
        $this->newLine();
        
        if ($summary['executed_count'] === 0) {
            if ($tag) {
                $this->warn("No runners found with tag: {$tag}");
            } else {
                $this->warn("No runners were executed");
            }
            return;
        }
        
        if ($summary['success']) {
            $this->info("✓ Successfully executed {$summary['executed_count']} runner(s)");
        } else {
            $this->error("✗ Executed {$summary['executed_count']} runner(s) with {$summary['error_count']} error(s)");
        }

        if (!empty($summary['executed_files'])) {
            $this->newLine();
            $this->line('Executed runners:');
            foreach ($summary['executed_files'] as $file) {
                $this->line("  - {$file}");
            }
        }

        if (!empty($summary['errors'])) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($summary['errors'] as $error) {
                $this->error("  - {$error['file']}: {$error['error']}");
            }
        }
    }
}
