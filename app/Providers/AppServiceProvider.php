<?php

namespace App\Providers;

use App\Services\OpenFda\OpenFdaClient;
use App\Services\Rag\Chunker;
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
        //
    }
}
