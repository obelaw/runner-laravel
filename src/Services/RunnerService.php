<?php

namespace Obelaw\Runner\Services;

use Obelaw\Runner\Runner;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Exception;
use Throwable;

class RunnerService
{
    private array $runnerPools;
    private array $executedFiles = [];
    private array $errors = [];

    public function __construct(array $runnerPools)
    {
        $this->validateRunnerPools($runnerPools);
        $this->runnerPools = $runnerPools;
    }

    /**
     * Run all runners from the configured pools.
     *
     * @param string|null $tag Filter runners by tag
     * @return array Summary of execution results
     */
    public function run(?string $tag = null): array
    {
        $this->reset();
        
        $runnersFiles = $this->collectRunnerFiles();
        
        if (empty($runnersFiles)) {
            Log::info('No runner files found in the specified paths.');
            return $this->getExecutionSummary();
        }

        Log::info('Starting runner execution', [
            'total_files' => count($runnersFiles),
            'tag_filter' => $tag
        ]);

        foreach ($runnersFiles as $file) {
            $this->executeRunner($file, $tag);
        }

        $summary = $this->getExecutionSummary();
        Log::info('Runner execution completed', $summary);

        return $summary;
    }

    /**
     * Validate runner pools configuration.
     *
     * @param array $runnerPools
     * @throws InvalidArgumentException
     */
    private function validateRunnerPools(array $runnerPools): void
    {
        if (empty($runnerPools)) {
            throw new InvalidArgumentException('Runner pools cannot be empty.');
        }

        foreach ($runnerPools as $path) {
            if (!is_string($path)) {
                throw new InvalidArgumentException('All runner pool paths must be strings.');
            }

            if (!is_dir($path)) {
                Log::warning("Runner pool path does not exist: {$path}");
            }
        }
    }

    /**
     * Collect all PHP files from runner pools.
     *
     * @return array
     */
    private function collectRunnerFiles(): array
    {
        $runnersFiles = [];

        foreach ($this->runnerPools as $path) {
            try {
                if (!is_dir($path)) {
                    Log::warning("Skipping non-existent path: {$path}");
                    continue;
                }

                $files = glob($path . '/*.php');
                if ($files === false) {
                    Log::warning("Failed to read files from path: {$path}");
                    continue;
                }
                
                $runnersFiles = array_merge($runnersFiles, $files);
                Log::debug("Found " . count($files) . " runner files in: {$path}");
                
            } catch (Exception $e) {
                Log::error("Error reading runner pool path: {$path}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return array_unique($runnersFiles);
    }

    /**
     * Execute a single runner file.
     *
     * @param string $file
     * @param string|null $tag
     */
    private function executeRunner(string $file, ?string $tag = null): void
    {
        try {
            if (!file_exists($file) || !is_readable($file)) {
                throw new Exception("File is not readable: {$file}");
            }

            $runner = $this->loadRunner($file);

            if (!$this->isValidRunner($runner)) {
                Log::warning("Invalid runner object in file: {$file}");
                return;
            }

            // Check if runner should run
            if ($runner instanceof Runner && !$runner->shouldRun()) {
                Log::debug("Skipping runner due to shouldRun() condition", [
                    'file' => basename($file)
                ]);
                return;
            }

            // Check tag filter if specified
            if ($tag !== null && !$this->matchesTag($runner, $tag)) {
                Log::debug("Skipping runner due to tag filter", [
                    'file' => basename($file),
                    'required_tag' => $tag,
                    'runner_tag' => $runner->tag ?? 'none'
                ]);
                return;
            }

            Log::info("Executing runner: " . basename($file), [
                'tag' => $runner->tag ?? 'none'
            ]);

            // Execute before hook if available
            if ($runner instanceof Runner || method_exists($runner, 'before')) {
                Log::debug("Executing before hook: " . basename($file));
                $runner->before();
            }

            // Execute main handle method
            $runner->handle();

            // Execute after hook if available
            if ($runner instanceof Runner || method_exists($runner, 'after')) {
                Log::debug("Executing after hook: " . basename($file));
                $runner->after();
            }

            $this->executedFiles[] = $file;

            Log::info("Successfully executed runner: " . basename($file));

        } catch (Throwable $e) {
            $error = [
                'file' => $file,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ];

            $this->errors[] = $error;
            
            Log::error("Failed to execute runner: " . basename($file), $error);
        }
    }

    /**
     * Load runner instance from file.
     *
     * @param string $file
     * @return mixed
     * @throws Exception
     */
    private function loadRunner(string $file)
    {
        try {
            // First require_once to avoid redeclaration issues
            require_once $file;
            // Then require to get the returned object
            return require $file;
        } catch (Throwable $e) {
            throw new Exception("Failed to load runner file: " . basename($file) . ". Error: " . $e->getMessage());
        }
    }

    /**
     * Check if the loaded object is a valid runner.
     *
     * @param mixed $runner
     * @return bool
     */
    private function isValidRunner($runner): bool
    {
        return is_object($runner) 
            && ($runner instanceof Runner || method_exists($runner, 'handle'));
    }

    /**
     * Check if runner matches the specified tag.
     *
     * @param object $runner
     * @param string $tag
     * @return bool
     */
    private function matchesTag(object $runner, string $tag): bool
    {
        if (!property_exists($runner, 'tag')) {
            return false;
        }

        return $runner->tag === $tag;
    }

    /**
     * Reset execution state.
     */
    private function reset(): void
    {
        $this->executedFiles = [];
        $this->errors = [];
    }

    /**
     * Get execution summary.
     *
     * @return array
     */
    private function getExecutionSummary(): array
    {
        return [
            'executed_count' => count($this->executedFiles),
            'error_count' => count($this->errors),
            'executed_files' => array_map('basename', $this->executedFiles),
            'errors' => $this->errors,
            'success' => empty($this->errors)
        ];
    }

    /**
     * Get list of executed files.
     *
     * @return array
     */
    public function getExecutedFiles(): array
    {
        return $this->executedFiles;
    }

    /**
     * Get list of errors that occurred during execution.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if execution was successful (no errors).
     *
     * @return bool
     */
    public function wasSuccessful(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get runner pools configuration.
     *
     * @return array
     */
    public function getRunnerPools(): array
    {
        return $this->runnerPools;
    }
}
