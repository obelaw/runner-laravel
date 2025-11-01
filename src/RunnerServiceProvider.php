<?php

namespace Obelaw\Runner;

use Illuminate\Support\ServiceProvider;
use Obelaw\Runner\Console\Commands\RunnerCommand;
use Obelaw\Runner\Console\Commands\RunnerMakeCommand;

class RunnerServiceProvider extends ServiceProvider
{

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            $this->commands([
                RunnerCommand::class,
                RunnerMakeCommand::class
            ]);
        }
    }
}
