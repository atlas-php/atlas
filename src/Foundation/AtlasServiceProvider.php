<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Foundation;

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;
use Atlasphp\Atlas\Agents\Services\AgentRegistry;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
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
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Services\ToolExtensionRegistry;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;
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
        $this->registerAgentServices();
        $this->registerToolServices();
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
                $app->make(PipelineRunner::class),
                $app->make(ProviderConfigService::class),
                $app->make(PrismBuilder::class),
            );
        });

        $this->app->singleton(ImageService::class, function (Container $app): ImageService {
            return new ImageService(
                $app->make(PrismBuilder::class),
                $app->make(ProviderConfigService::class),
                $app->make(PipelineRunner::class),
            );
        });

        $this->app->singleton(SpeechService::class, function (Container $app): SpeechService {
            return new SpeechService(
                $app->make(PrismBuilder::class),
                $app->make(ProviderConfigService::class),
                $app->make(PipelineRunner::class),
            );
        });

        $this->app->singleton(UsageExtractorRegistry::class, function (): UsageExtractorRegistry {
            return new UsageExtractorRegistry;
        });

        $this->app->singleton(AtlasManager::class, function (Container $app): AtlasManager {
            return new AtlasManager(
                $app->make(AgentResolver::class),
                $app->make(AgentExecutorContract::class),
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

        $registry->define(
            'agent.on_error',
            'Pipeline executed when agent execution fails',
        );

        $registry->define(
            'tool.on_error',
            'Pipeline executed when tool execution fails',
        );

        // Embedding service pipelines
        $registry->define(
            'embedding.before_generate',
            'Pipeline executed before generating a single embedding',
        );

        $registry->define(
            'embedding.after_generate',
            'Pipeline executed after generating a single embedding',
        );

        $registry->define(
            'embedding.before_generate_batch',
            'Pipeline executed before generating batch embeddings',
        );

        $registry->define(
            'embedding.after_generate_batch',
            'Pipeline executed after generating batch embeddings',
        );

        $registry->define(
            'embedding.on_error',
            'Pipeline executed when embedding generation fails',
        );

        // Image service pipelines
        $registry->define(
            'image.before_generate',
            'Pipeline executed before generating an image',
        );

        $registry->define(
            'image.after_generate',
            'Pipeline executed after generating an image',
        );

        $registry->define(
            'image.on_error',
            'Pipeline executed when image generation fails',
        );

        // Speech service pipelines
        $registry->define(
            'speech.before_generate',
            'Pipeline executed before generating speech from text',
        );

        $registry->define(
            'speech.after_generate',
            'Pipeline executed after generating speech from text',
        );

        $registry->define(
            'speech.before_transcribe',
            'Pipeline executed before speech-to-text transcription',
        );

        $registry->define(
            'speech.after_transcribe',
            'Pipeline executed after speech-to-text transcription',
        );

        $registry->define(
            'speech.on_error',
            'Pipeline executed when speech operation fails',
        );

        // Streaming pipelines (streaming-specific hooks only)
        $registry->define(
            'stream.on_event',
            'Pipeline executed for each stream event',
        );

        $registry->define(
            'stream.after_complete',
            'Pipeline executed after streaming completes',
        );
    }

    /**
     * Register agent services.
     */
    protected function registerAgentServices(): void
    {
        $this->app->singleton(AgentRegistryContract::class, function (Container $app): AgentRegistry {
            return new AgentRegistry($app);
        });

        $this->app->singleton(AgentResolver::class, function (Container $app): AgentResolver {
            return new AgentResolver(
                $app->make(AgentRegistryContract::class),
                $app,
            );
        });

        $this->app->singleton(SystemPromptBuilder::class, function (Container $app): SystemPromptBuilder {
            return new SystemPromptBuilder(
                $app->make(PipelineRunner::class),
            );
        });

        $this->app->singleton(AgentExecutorContract::class, function (Container $app): AgentExecutor {
            return new AgentExecutor(
                $app->make(PrismBuilderContract::class),
                $app->make(ToolBuilder::class),
                $app->make(SystemPromptBuilder::class),
                $app->make(PipelineRunner::class),
                $app->make(UsageExtractorRegistry::class),
                $app->make(ProviderConfigService::class),
            );
        });

        $this->app->singleton(AgentExtensionRegistry::class, function (): AgentExtensionRegistry {
            return new AgentExtensionRegistry;
        });
    }

    /**
     * Register tool services.
     */
    protected function registerToolServices(): void
    {
        $this->app->singleton(ToolRegistryContract::class, function (Container $app): ToolRegistry {
            return new ToolRegistry($app);
        });

        $this->app->singleton(ToolExecutor::class, function (Container $app): ToolExecutor {
            return new ToolExecutor(
                $app->make(PipelineRunner::class),
            );
        });

        $this->app->singleton(ToolBuilder::class, function (Container $app): ToolBuilder {
            return new ToolBuilder(
                $app->make(ToolRegistryContract::class),
                $app->make(ToolExecutor::class),
                $app,
            );
        });

        $this->app->singleton(ToolExtensionRegistry::class, function (): ToolExtensionRegistry {
            return new ToolExtensionRegistry;
        });
    }
}
