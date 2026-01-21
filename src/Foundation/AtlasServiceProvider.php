<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Foundation;

use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\EmbeddingProviderContract;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Embedding\PrismEmbeddingProvider;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Services\EmbeddingService;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\PrismBuilder;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Atlasphp\Atlas\Providers\Services\SpeechService;
use Atlasphp\Atlas\Providers\Services\UsageExtractorRegistry;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Atlas package.
 *
 * Registers all services, bindings, and configuration for the package.
 */
class AtlasServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/atlas.php', 'atlas');

        $this->registerFoundationServices();
        $this->registerProviderServices();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishConfig();
        $this->defineCorePipelines();
    }

    /**
     * Register foundation services.
     */
    protected function registerFoundationServices(): void
    {
        $this->app->singleton(PipelineRegistry::class, function (): PipelineRegistry {
            return new PipelineRegistry;
        });

        $this->app->singleton(PipelineRunner::class, function (Container $app): PipelineRunner {
            return new PipelineRunner(
                $app->make(PipelineRegistry::class),
                $app,
            );
        });
    }

    /**
     * Register provider services.
     */
    protected function registerProviderServices(): void
    {
        $this->app->singleton(ProviderConfigService::class, function (Container $app): ProviderConfigService {
            return new ProviderConfigService(
                $app->make(ConfigRepository::class),
            );
        });

        $this->app->singleton(PrismBuilder::class, function (): PrismBuilder {
            return new PrismBuilder;
        });

        $this->app->bind(PrismBuilderContract::class, PrismBuilder::class);

        $this->app->singleton(EmbeddingProviderContract::class, function (Container $app): EmbeddingProviderContract {
            $configService = $app->make(ProviderConfigService::class);
            $config = $configService->getEmbeddingConfig();

            return new PrismEmbeddingProvider(
                $app->make(PrismBuilder::class),
                $config['provider'],
                $config['model'],
                $config['dimensions'],
                $config['batch_size'],
            );
        });

        $this->app->singleton(EmbeddingService::class, function (Container $app): EmbeddingService {
            return new EmbeddingService(
                $app->make(EmbeddingProviderContract::class),
            );
        });

        $this->app->singleton(ImageService::class, function (Container $app): ImageService {
            return new ImageService(
                $app->make(PrismBuilder::class),
                $app->make(ProviderConfigService::class),
            );
        });

        $this->app->singleton(SpeechService::class, function (Container $app): SpeechService {
            return new SpeechService(
                $app->make(PrismBuilder::class),
                $app->make(ProviderConfigService::class),
            );
        });

        $this->app->singleton(UsageExtractorRegistry::class, function (): UsageExtractorRegistry {
            return new UsageExtractorRegistry;
        });

        $this->app->singleton(AtlasManager::class, function (Container $app): AtlasManager {
            return new AtlasManager(
                $app->make(EmbeddingService::class),
                $app->make(ImageService::class),
                $app->make(SpeechService::class),
            );
        });
    }

    /**
     * Publish configuration files.
     */
    protected function publishConfig(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/atlas.php' => config_path('atlas.php'),
            ], 'atlas-config');
        }
    }

    /**
     * Define core pipelines for the package.
     */
    protected function defineCorePipelines(): void
    {
        $registry = $this->app->make(PipelineRegistry::class);

        $registry->define(
            'agent.before_execute',
            'Pipeline executed before agent runs',
        );

        $registry->define(
            'agent.after_execute',
            'Pipeline executed after agent completes',
        );

        $registry->define(
            'agent.system_prompt.before_build',
            'Pipeline executed before building system prompt',
        );

        $registry->define(
            'agent.system_prompt.after_build',
            'Pipeline executed after building system prompt',
        );

        $registry->define(
            'tool.before_execute',
            'Pipeline executed before tool runs',
        );

        $registry->define(
            'tool.after_execute',
            'Pipeline executed after tool completes',
        );
    }
}
