<?php

namespace App\Providers;

use App\Services\OpenFda\OpenFdaClient;
use App\Services\Rag\Chunker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Services that take plain config arrays need explicit construction.
        $this->app->singleton(OpenFdaClient::class, fn () => new OpenFdaClient(
            config('kardiorag.openfda')
        ));

        $this->app->singleton(Chunker::class, fn () => new Chunker(
            (int) config('kardiorag.chunk.size', 900),
            (int) config('kardiorag.chunk.overlap', 150),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate-limit the ask endpoint (per-IP) to protect the model backend.
        RateLimiter::for('ask', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Default limiter for the API middleware group (per-IP).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
