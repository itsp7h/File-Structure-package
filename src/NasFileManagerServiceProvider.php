<?php

namespace P7H\NasFileManager;

use Illuminate\Support\ServiceProvider;

class NasFileManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/nas-file-manager.php',
            'nas-file-manager'
        );

        $this->app->singleton(NasStorageService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/nas.php');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'nas-file-manager');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/nas-file-manager.php' => config_path('nas-file-manager.php'),
            ], 'nas-file-manager-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/nas-file-manager'),
            ], 'nas-file-manager-views');
        }
    }
}
