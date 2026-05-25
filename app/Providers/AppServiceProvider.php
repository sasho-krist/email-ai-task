<?php

namespace App\Providers;

use App\Contracts\EmailToTaskEvaluator;
use App\Services\Ai\MockEmailToTaskEvaluator;
use App\Services\Ai\OpenAiEmailToTaskEvaluator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Schema::defaultStringLength(191);

        $this->app->bind(EmailToTaskEvaluator::class, function () {
            return match (config('ai.provider')) {
                'openai' => $this->app->make(OpenAiEmailToTaskEvaluator::class),
                'mock' => $this->app->make(MockEmailToTaskEvaluator::class),
                default => throw new InvalidArgumentException('Unsupported AI provider: '.config('ai.provider')),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
