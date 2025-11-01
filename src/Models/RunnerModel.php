<?php

namespace Obelaw\Runner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Runner\Runner;

class RunnerModel extends Model
{
    protected $table = 'runners';

    protected $fillable = [
        'name',
        'tag',
        'description',
        'priority',
        'type',
        'executed_at',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
    ];

    /**
     * Get all logs for this runner.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(RunnerLog::class, 'runner_name', 'name');
    }

    /**
     * Check if a runner has been executed.
     *
     * @param string $name
     * @return bool
     */
    public static function hasBeenExecuted(string $name): bool
    {
        return static::where('name', $name)->exists();
    }

    /**
     * Mark a runner as executed.
     *
     * @param string $name
     * @param array $data
     * @return static
     */
    public static function markAsExecuted(string $name, array $data = []): static
    {
        return static::updateOrCreate(
            ['name' => $name],
            array_merge([
                'executed_at' => now(),
                'type' => $data['type'] ?? Runner::TYPE_ONCE,
            ], $data)
        );
    }

    /**
     * Get all executed runners by tag.
     *
     * @param string $tag
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getByTag(string $tag)
    {
        return static::where('tag', $tag)->get();
    }

    /**
     * Get all runners that should only run once.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getOnceRunners()
    {
        return static::where('type', Runner::TYPE_ONCE)->get();
    }

    /**
     * Get all runners that can run always.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAlwaysRunners()
    {
        return static::where('type', Runner::TYPE_ALWAYS)->get();
    }

    /**
     * Get the last execution log.
     */
    public function lastLog()
    {
        return $this->logs()->latest()->first();
    }

    /**
     * Get failed execution logs.
     */
    public function failedLogs()
    {
        return $this->logs()->failed()->get();
    }
}