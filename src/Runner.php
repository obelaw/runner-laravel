<?php

namespace Obelaw\Runner;

abstract class Runner
{
    /**
     * The tag of the runner for filtering execution.
     *
     * @var string|null
     */
    public ?string $tag = null;

    /**
     * The priority of the runner (lower numbers run first).
     *
     * @var int
     */
    public int $priority = 0;

    /**
     * The description of what this runner does.
     *
     * @var string|null
     */
    public ?string $description = null;

    /**
     * Whether this runner can be executed multiple times.
     *
     * @var bool
     */
    protected bool $allowMultipleRuns = false;

    /**
     * Execute the runner logic.
     *
     * @return void
     */
    abstract public function handle(): void;

    /**
     * Determine if the runner should be executed.
     * Override this method to add conditional execution logic.
     *
     * @return bool
     */
    public function shouldRun(): bool
    {
        return true;
    }

    /**
     * Hook that runs before the main handle method.
     *
     * @return void
     */
    public function before(): void
    {
        //
    }

    /**
     * Hook that runs after the main handle method.
     *
     * @return void
     */
    public function after(): void
    {
        //
    }

    /**
     * Get the runner tag.
     *
     * @return string|null
     */
    public function getTag(): ?string
    {
        return $this->tag;
    }

    /**
     * Set the runner tag.
     *
     * @param string|null $tag
     * @return static
     */
    public function setTag(?string $tag): static
    {
        $this->tag = $tag;
        return $this;
    }

    /**
     * Get the runner priority.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Set the runner priority.
     *
     * @param int $priority
     * @return static
     */
    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Get the runner description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Check if multiple runs are allowed.
     *
     * @return bool
     */
    public function allowsMultipleRuns(): bool
    {
        return $this->allowMultipleRuns;
    }

    /**
     * Get runner information as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'class' => static::class,
            'tag' => $this->tag,
            'priority' => $this->priority,
            'description' => $this->description,
            'allow_multiple_runs' => $this->allowMultipleRuns,
        ];
    }
}
