<?php

namespace Yannelli\Schematic;

use Illuminate\Support\ServiceProvider;

class SchematicServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/schematic.php', 'schematic');

        $this->app->singleton(Compiler::class, fn () => new Compiler());

        $this->app->singleton(Schematic::class, fn ($app) => new Schematic(
            compiler: $app->make(Compiler::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishesMigrations([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'schematic-migrations');

            $this->publishes([
                __DIR__ . '/../config/schematic.php' => config_path('schematic.php'),
            ], 'schematic-config');
        }
    }
}
