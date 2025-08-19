<?php

namespace Zuse\LaravelRbacAuto;

use Illuminate\Support\ServiceProvider;
use Zuse\LaravelRbacAuto\Commands\ZuseIntegrateCommand;
use Zuse\LaravelRbacAuto\Services\CodeGenerator;
use Zuse\LaravelRbacAuto\Services\ConfigurationManager;
use Zuse\LaravelRbacAuto\Services\TestRunner;

class ZuseRbacAutoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        // Register service classes
        $this->app->singleton(CodeGenerator::class, function ($app) {
            return new CodeGenerator();
        });

        $this->app->singleton(ConfigurationManager::class, function ($app) {
            return new ConfigurationManager();
        });

        $this->app->singleton(TestRunner::class, function ($app) {
            return new TestRunner();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ZuseIntegrateCommand::class,
            ]);
        }

        // Publish stubs
        $this->publishes([
            __DIR__ . '/../stubs' => resource_path('stubs/zuse-rbac'),
        ], 'zuse-rbac-stubs');
    }
}