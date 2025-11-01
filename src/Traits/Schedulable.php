<?php

namespace Obelaw\Runner\Traits;

use Carbon\Carbon;
use Obelaw\Runner\Models\RunnerModel;

trait Schedulable
{
    /**
     * The cron expression for scheduling.
     * Examples:
     * - '* * * * *' (every minute)
     * - '0 * * * *' (hourly)
     * - '0 0 * * *' (daily at midnight)
     * - '0 0 * * 0' (weekly on Sunday)
     * - '0 0 1 * *' (monthly on the 1st)
     *
     * @var string|null
     * @example protected ?string $schedule = null;
     */
    protected ?string $schedule = null;

    /**
     * Check if the runner should run based on schedule.
     *
     * @return bool
     */
    public function shouldRunBySchedule(): bool
    {
        if (!$this->schedule) {
            return true; // No schedule defined, always run
        }

        $lastRun = $this->getLastRunTime();

        // If never run, check if current time matches schedule
        if (!$lastRun) {
            return $this->isDue();
        }

        // Check if enough time has passed based on schedule
        return $this->isDue() && !$this->hasRunInCurrentPeriod($lastRun);
    }

    /**
     * Check if the current time matches the cron schedule.
     *
     * @return bool
     */
    protected function isDue(): bool
    {
        if (!$this->schedule) {
            return true;
        }

        return $this->cronMatches($this->schedule, Carbon::now());
    }

    /**
     * Check if the runner has already run in the current scheduled period.
     *
     * @param Carbon $lastRun
     * @return bool
     */
    protected function hasRunInCurrentPeriod(Carbon $lastRun): bool
    {
        $now = Carbon::now();

        // Parse cron expression
        $parts = explode(' ', $this->schedule);
        if (count($parts) !== 5) {
            return false;
        }

        [$minute, $hour, $day, $month, $dayOfWeek] = $parts;

        // Check based on the most specific part of the schedule
        if ($minute !== '*') {
            // Runs at specific minute(s), check if run in current minute
            return $lastRun->isSameMinute($now);
        }

        if ($hour !== '*') {
            // Runs at specific hour(s), check if run in current hour
            return $lastRun->isSameHour($now);
        }

        if ($day !== '*') {
            // Runs on specific day(s), check if run today
            return $lastRun->isSameDay($now);
        }

        if ($month !== '*') {
            // Runs in specific month(s), check if run this month
            return $lastRun->isSameMonth($now);
        }

        if ($dayOfWeek !== '*') {
            // Runs on specific day(s) of week, check if run on same day of week
            return $lastRun->isSameDay($now);
        }

        return false;
    }

    /**
     * Get the last run time from database.
     *
     * @return Carbon|null
     */
    protected function getLastRunTime(): ?Carbon
    {
        $runnerName = $this->getRunnerName();
        $runner = RunnerModel::where('name', $runnerName)->first();

        return $runner?->executed_at;
    }

    /**
     * Get the runner name (filename).
     *
     * @return string
     */
    protected function getRunnerName(): string
    {
        // Get the filename from backtrace
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $item) {
            if (isset($item['file']) && str_ends_with($item['file'], '.php')) {
                return basename($item['file']);
            }
        }

        return 'unknown_runner.php';
    }

    /**
     * Check if current time matches cron expression.
     *
     * @param string $cronExpression
     * @param Carbon $now
     * @return bool
     */
    protected function cronMatches(string $cronExpression, Carbon $now): bool
    {
        $parts = explode(' ', $cronExpression);
        if (count($parts) !== 5) {
            return false;
        }

        [$minute, $hour, $day, $month, $dayOfWeek] = $parts;

        return $this->matchesPart($minute, $now->minute, 0, 59) &&
            $this->matchesPart($hour, $now->hour, 0, 23) &&
            $this->matchesPart($day, $now->day, 1, 31) &&
            $this->matchesPart($month, $now->month, 1, 12) &&
            $this->matchesPart($dayOfWeek, $now->dayOfWeek, 0, 6);
    }

    /**
     * Check if a value matches a cron part.
     *
     * @param string $expression
     * @param int $value
     * @param int $min
     * @param int $max
     * @return bool
     */
    protected function matchesPart(string $expression, int $value, int $min, int $max): bool
    {
        // Wildcard matches everything
        if ($expression === '*') {
            return true;
        }

        // Handle step values (e.g., */5)
        if (str_contains($expression, '/')) {
            [$range, $step] = explode('/', $expression);
            if ($range === '*') {
                return $value % (int)$step === 0;
            }
        }

        // Handle ranges (e.g., 1-5)
        if (str_contains($expression, '-')) {
            [$start, $end] = explode('-', $expression);
            return $value >= (int)$start && $value <= (int)$end;
        }

        // Handle lists (e.g., 1,3,5)
        if (str_contains($expression, ',')) {
            $values = array_map('intval', explode(',', $expression));
            return in_array($value, $values);
        }

        // Single value
        return $value === (int)$expression;
    }

    /**
     * Set the schedule using cron expression.
     *
     * @param string $expression
     * @return $this
     */
    public function cron(string $expression): self
    {
        $this->schedule = $expression;
        return $this;
    }

    /**
     * Schedule to run every minute.
     *
     * @return $this
     */
    public function everyMinute(): self
    {
        $this->schedule = '* * * * *';
        return $this;
    }

    /**
     * Schedule to run every N minutes.
     *
     * @param int $minutes
     * @return $this
     */
    public function everyMinutes(int $minutes): self
    {
        $this->schedule = "*/{$minutes} * * * *";
        return $this;
    }

    /**
     * Schedule to run hourly.
     *
     * @return $this
     */
    public function hourly(): self
    {
        $this->schedule = '0 * * * *';
        return $this;
    }

    /**
     * Schedule to run every N hours.
     *
     * @param int $hours
     * @return $this
     */
    public function everyHours(int $hours): self
    {
        $this->schedule = "0 */{$hours} * * *";
        return $this;
    }

    /**
     * Schedule to run daily at midnight.
     *
     * @return $this
     */
    public function daily(): self
    {
        $this->schedule = '0 0 * * *';
        return $this;
    }

    /**
     * Schedule to run daily at specific time.
     *
     * @param string $time (HH:MM format)
     * @return $this
     */
    public function dailyAt(string $time): self
    {
        [$hour, $minute] = explode(':', $time);
        $this->schedule = "{$minute} {$hour} * * *";
        return $this;
    }

    /**
     * Schedule to run weekly on Sunday at midnight.
     *
     * @return $this
     */
    public function weekly(): self
    {
        $this->schedule = '0 0 * * 0';
        return $this;
    }

    /**
     * Schedule to run weekly on specific day.
     *
     * @param int $day (0 = Sunday, 6 = Saturday)
     * @return $this
     */
    public function weeklyOn(int $day): self
    {
        $this->schedule = "0 0 * * {$day}";
        return $this;
    }

    /**
     * Schedule to run monthly on the first day at midnight.
     *
     * @return $this
     */
    public function monthly(): self
    {
        $this->schedule = '0 0 1 * *';
        return $this;
    }

    /**
     * Schedule to run monthly on specific day.
     *
     * @param int $day
     * @return $this
     */
    public function monthlyOn(int $day): self
    {
        $this->schedule = "0 0 {$day} * *";
        return $this;
    }

    /**
     * Get the schedule expression.
     *
     * @return string|null
     */
    public function getSchedule(): ?string
    {
        return $this->schedule;
    }
}
