<?php

namespace Obelaw\Runner\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Obelaw\Runner\Models\RunnerModel;
use Obelaw\Runner\Models\RunnerLog;
use Obelaw\Runner\Runner;
use Throwable;

class RunnerService
{
    private array $runnerPools;
    private array $executedFiles = [];
    private array $skippedFiles = [];
    private array $errors = [];
    private bool $trackExecutions = true;
    private bool $force = false;

    public function __construct(array $runnerPools)
    {
        $this->validateRunnerPools($runnerPools);
        $this->runnerPools = $runnerPools;
    }

    /**
     * Enable or disable execution tracking.
     *
     * @param bool $track
     * @return $this
     */
    public function trackExecutions(bool $track = true): self
    {
        $this->trackExecutions = $track;
        return $this;
    }

    /**
     * Force execution of all runners.
     *
     * @param bool $force
     * @return $this
     */
    public function force(bool $force = true): self
    {
        $this->force = $force;
        return $this;
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

        // Sort runners by filename (timestamp-based names will be sorted chronologically)
        $runnersFiles = $this->sortRunnersByName($runnersFiles);

        Log::info('Starting runner execution', [
            'total_files' => count($runnersFiles),
            'tag_filter' => $tag,
            'force' => $this->force
        ]);

        foreach ($runnersFiles as $file) {
            $this->executeRunner($file, $tag);
        }

        $summary = $this->getExecutionSummary();
        Log::info('Runner execution completed', $summary);

        return $summary;
    }

    /**
     * Run a specific runner by name.
     *
     * @param string $runnerName The filename of the runner (e.g., '2024_11_01_120000_create_categories.php')
     * @return array Summary of execution results
     * @throws Exception If runner file not found
     */
    public function runByName(string $runnerName): array
    {
        $this->reset();

        // Find the runner file across all pools
        $runnerFile = $this->findRunnerByName($runnerName);

        if (!$runnerFile) {
            throw new Exception("Runner file not found: {$runnerName}");
        }

        Log::info("Running specific runner: {$runnerName}");

        // Execute the runner
        $this->executeRunner($runnerFile);

        $summary = $this->getExecutionSummary();
        Log::info('Runner execution completed', $summary);

        return $summary;
    }

    /**
     * Find a runner file by name across all pools.
     *
     * @param string $runnerName
     * @return string|null Full path to the runner file, or null if not found
     */
    private function findRunnerByName(string $runnerName): ?string
    {
        // Normalize the runner name (remove .php if present, add it back)
        $runnerName = str_replace('.php', '', $runnerName) . '.php';

        foreach ($this->runnerPools as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $filePath = $path . DIRECTORY_SEPARATOR . $runnerName;
            
            if (file_exists($filePath)) {
                return $filePath;
            }
        }

        return null;
    }

    /**
     * Check if a runner exists by name.
     *
     * @param string $runnerName
     * @return bool
     */
    public function runnerExists(string $runnerName): bool
    {
        return $this->findRunnerByName($runnerName) !== null;
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
     * Sort runners by filename across all pools.
     *
     * @param array $runnersFiles
     * @return array
     */
    private function sortRunnersByName(array $runnersFiles): array
    {
        usort($runnersFiles, function ($a, $b) {
            return strcmp(basename($a), basename($b));
        });

        Log::debug('Sorted runners by filename', [
            'first' => !empty($runnersFiles) ? basename($runnersFiles[0]) : null,
            'last' => !empty($runnersFiles) ? basename(end($runnersFiles)) : null,
        ]);

        return $runnersFiles;
    }

    /**
     * Execute a single runner file.
     *
     * @param string $file
     * @param string|null $tag
     */
    private function executeRunner(string $file, ?string $tag = null): void
    {
        $runnerName = basename($file);
        $runnerLog = null;

        try {
            if (!file_exists($file) || !is_readable($file)) {
                throw new Exception("File is not readable: {$file}");
            }

            $runner = $this->loadRunner($file);

            if (!$this->isValidRunner($runner)) {
                Log::warning("Invalid runner object in file: {$file}");
                return;
            }

            // Check if runner should be skipped based on type
            if (!$this->force && $this->trackExecutions && $this->shouldSkipBasedOnType($runnerName, $runner)) {
                return;
            }

            // Check if runner should run
            if ($runner instanceof Runner && !$runner->shouldRun()) {
                Log::debug("Skipping runner due to shouldRun() condition", [
                    'file' => $runnerName
                ]);
                $this->skippedFiles[] = $file;
                return;
            }

            // Check tag filter if specified
            if ($tag !== null && !$this->matchesTag($runner, $tag)) {
                Log::debug("Skipping runner due to tag filter", [
                    'file' => $runnerName,
                    'required_tag' => $tag,
                    'runner_tag' => $runner->tag ?? 'none'
                ]);
                $this->skippedFiles[] = $file;
                return;
            }

            // Start logging execution
            if ($this->trackExecutions) {
                $runnerLog = RunnerLog::logStart($runnerName, [
                    'tag' => $runner instanceof Runner ? $runner->getTag() : ($runner->tag ?? null),
                    'type' => $runner instanceof Runner ? $runner->getType() : Runner::TYPE_ONCE,
                ]);
            }

            Log::info("Executing runner: {$runnerName}", [
                'tag' => $runner->tag ?? 'none',
                'type' => $runner instanceof Runner ? $runner->getType() : 'unknown'
            ]);

            // Capture output
            ob_start();

            // Execute before hook if available
            if ($runner instanceof Runner || method_exists($runner, 'before')) {
                Log::debug("Executing before hook: {$runnerName}");
                $runner->before();
            }

            // Execute main handle method
            $runner->handle();

            // Execute after hook if available
            if ($runner instanceof Runner || method_exists($runner, 'after')) {
                Log::debug("Executing after hook: {$runnerName}");
                $runner->after();
            }

            // Get captured output
            $output = ob_get_clean();

            // Mark log as completed
            if ($runnerLog) {
                $runnerLog->markCompleted($output);
            }

            // Track execution
            if ($this->trackExecutions) {
                $this->trackExecution($runnerName, $runner);
            }

            $this->executedFiles[] = $file;

            Log::info("Successfully executed runner: {$runnerName}");

        } catch (Throwable $e) {
            // Clean output buffer if active
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Mark log as failed
            if ($runnerLog) {
                $runnerLog->markFailed($e->getMessage() . ' at line ' . $e->getLine());
            }

            $error = [
                'file' => $file,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ];

            $this->errors[] = $error;
            
            Log::error("Failed to execute runner: {$runnerName}", $error);
        }
    }

    /**
     * Determine if runner should be skipped based on type.
     *
     * @param string $runnerName
     * @param object $runner
     * @return bool
     */
    private function shouldSkipBasedOnType(string $runnerName, object $runner): bool
    {
        if (!RunnerModel::hasBeenExecuted($runnerName)) {
            return false;
        }

        // If runner is TYPE_ONCE, skip if already executed
        if ($runner instanceof Runner && $runner->isTypeOnce()) {
            Log::debug("Skipping TYPE_ONCE runner that was already executed: {$runnerName}");
            $this->skippedFiles[] = $runnerName;
            return true;
        }

        // TYPE_ALWAYS runners are never skipped (except with force flag logic)
        if ($runner instanceof Runner && $runner->isTypeAlways()) {
            Log::debug("Re-executing TYPE_ALWAYS runner: {$runnerName}");
            return false;
        }

        // For non-Runner objects, check if they have a type property
        if (property_exists($runner, 'type')) {
            if ($runner->getType() === Runner::TYPE_ONCE) {
                Log::debug("Skipping TYPE_ONCE runner that was already executed: {$runnerName}");
                $this->skippedFiles[] = $runnerName;
                return true;
            }
        } else {
            // Default behavior: skip if already executed
            Log::debug("Skipping already executed runner (default TYPE_ONCE): {$runnerName}");
            $this->skippedFiles[] = $runnerName;
            return true;
        }

        return false;
    }

    /**
     * Track runner execution in database.
     *
     * @param string $name
     * @param object $runner
     */
    private function trackExecution(string $name, object $runner): void
    {
        try {
            $data = [];

            if ($runner instanceof Runner) {
                $data = [
                    'tag' => $runner->getTag(),
                    'description' => $runner->getDescription(),
                    'priority' => $runner->getPriority(),
                    'type' => $runner->getType(),
                ];
            } elseif (property_exists($runner, 'tag')) {
                $data['tag'] = $runner->tag;
                if (property_exists($runner, 'type')) {
                    $data['type'] = $runner->type;
                }
            }

            RunnerModel::markAsExecuted($name, $data);
            
            Log::debug("Tracked execution for runner: {$name}", ['type' => $data['type'] ?? 'once']);
        } catch (Throwable $e) {
            Log::warning("Failed to track runner execution: {$name}", [
                'error' => $e->getMessage()
            ]);
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
        $this->skippedFiles = [];
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
            'skipped_count' => count($this->skippedFiles),
            'error_count' => count($this->errors),
            'executed_files' => array_map('basename', $this->executedFiles),
            'skipped_files' => array_map('basename', $this->skippedFiles),
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
     * Get list of skipped files.
     *
     * @return array
     */
    public function getSkippedFiles(): array
    {
        return $this->skippedFiles;
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
