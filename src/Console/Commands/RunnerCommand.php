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
    protected $signature = 'runner:run 
                            {name? : Specific runner name to execute}
                            {--tag= : Filter runners by tag}
                            {--force : Force re-execution of all runners (including TYPE_ONCE)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute runners from configured pools';

    public function handle(): void
    {
        $name = $this->argument('name');
        $tag = $this->option('tag');
        $force = $this->option('force');
        
        $runnerService = new RunnerService(RunnerPool::getPaths());
        
        if ($force) {
            $runnerService->force(true);
            $this->warn("Force mode: Re-executing all runners including TYPE_ONCE");
        }

        // Run specific runner by name
        if ($name) {
            $this->info("Running specific runner: {$name}");
            
            try {
                $summary = $runnerService->runByName($name);
                $this->displaySummary($summary, $tag);
            } catch (\Exception $e) {
                $this->error("Error: {$e->getMessage()}");
                return;
            }
            
            return;
        }

        // Run all runners or by tag
        if ($tag) {
            $this->info("Running runners with tag: {$tag}");
        } else {
            $this->info("Running all runners...");
        }

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
        
        if ($summary['executed_count'] === 0 && $summary['skipped_count'] === 0) {
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

        if ($summary['skipped_count'] > 0) {
            $this->warn("⊘ Skipped {$summary['skipped_count']} runner(s) (TYPE_ONCE already executed)");
        }

        if (!empty($summary['executed_files'])) {
            $this->newLine();
            $this->line('Executed runners:');
            foreach ($summary['executed_files'] as $file) {
                $this->line("  ✓ {$file}");
            }
        }

        if (!empty($summary['skipped_files'])) {
            $this->newLine();
            $this->line('Skipped runners:');
            foreach ($summary['skipped_files'] as $file) {
                $this->line("  ⊘ {$file}");
            }
        }

        if (!empty($summary['errors'])) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($summary['errors'] as $error) {
                $this->error("  ✗ " . basename($error['file']) . ": {$error['error']}");
            }
        }
    }
}
