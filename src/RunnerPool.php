<?php

namespace Obelaw\Runner;

class RunnerPool
{
    /**
     * These paths for all pools of runners.
     *
     * @var array
     */
    protected static array $paths = [];

    /**
     * Add a path to the paths array.
     *
     * @param string $path
     */
    public static function addPath(string $path): void
    {
        array_push(self::$paths, $path);
    }

    /**
     * Get the paths array.
     *
     * @return array
     */
    public static function getPaths(): array
    {
        return self::$paths;
    }
}
