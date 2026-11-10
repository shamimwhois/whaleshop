<?php

namespace App\Providers;

use App\Services\Ai\Rag\EmbedderService;
use App\Services\Ai\Rag\JsonVectorDriver;
use App\Services\Ai\Rag\NativeVectorDriver;
use App\Services\Ai\Rag\RagSearchService;
use App\Services\Ai\Rag\VectorDriverFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the AI commerce layer (RAG, sales agent, tools) into the container.
 *
 * Follows the same module pattern as App\Providers\SecurityServiceProvider:
 * config is merged from a dedicated file and every service is bound as a
 * singleton so the driver resolution is performed once per request.
 */
final class AiCommerceServiceProvider extends ServiceProvider
{
    /** @var array<int, class-string> */
    private const SINGLETONS = [
        JsonVectorDriver::class,
        NativeVectorDriver::class,
        VectorDriverFactory::class,
        EmbedderService::class,
        RagSearchService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(config_path('ai_commerce.php'), 'ai_commerce');

        foreach (self::SINGLETONS as $class) {
            $this->app->singleton($class);
        }
    }
}
