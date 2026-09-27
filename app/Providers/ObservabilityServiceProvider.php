<?php

namespace App\Providers;

use App\Observability\Telemetry;
use App\Observability\TelemetryFactory;
use Illuminate\Support\ServiceProvider;

class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Telemetry::class, fn () => TelemetryFactory::make(
            config('observability'),
            $this->app->runningInConsole(),
            (string) config('app.env'),
        ));
    }

    public function boot(): void
    {
        $this->app->terminating(function (): void {
            if ($this->app->resolved(Telemetry::class)) {
                $this->app->make(Telemetry::class)->shutdown();
            }
        });
    }
}
