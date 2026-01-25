<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;
use Atlasphp\Atlas\Agents\Services\AgentRegistry;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Services\MediaConverter;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\Support\ClassDiscovery;
use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Atlas package.
 *
 * Registers all services, bindings, and configuration for the package.
 * Acts as a thin wrapper around Prism with pipeline support for observability.
 */
class AtlasServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atlas.php', 'atlas');

        $this->registerFoundationServices();
        $this->registerAgentServices();
        $this->registerToolServices();
        $this->registerAtlasManager();
        $this->registerDiscoveryService();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishConfig();
        $this->defineCorePipelines();
        $this->configurePipelinesState();
        $this->discoverAgents();
        $this->discoverTools();
    }

    /**
     * Register foundation services.
     */
    protected function registerFoundationServices(): void
    {
        $this->app->singleton(PipelineRegistry::class, function (Container $app): PipelineRegistry {
            $registry = new PipelineRegistry;
            $registry->setContainer($app);

            return $registry;
        });

        $this->app->singleton(PipelineRunner::class, function (Container $app): PipelineRunner {
            return new PipelineRunner(
                $app->make(PipelineRegistry::class),
                $app,
            );
        });
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
                $app->make(AgentExtensionRegistry::class),
            );
        });

        $this->app->singleton(SystemPromptBuilder::class, function (Container $app): SystemPromptBuilder {
            return new SystemPromptBuilder(
                $app->make(PipelineRunner::class),
            );
        });

        $this->app->singleton(MediaConverter::class, function (): MediaConverter {
            return new MediaConverter;
        });

        $this->app->singleton(AgentExecutorContract::class, function (Container $app): AgentExecutor {
            return new AgentExecutor(
                $app->make(ToolBuilder::class),
                $app->make(SystemPromptBuilder::class),
                $app->make(PipelineRunner::class),
                $app->make(MediaConverter::class),
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
                $app->make(PipelineRunner::class),
            );
        });
    }

    /**
     * Register the AtlasManager.
     */
    protected function registerAtlasManager(): void
    {
        $this->app->singleton(AtlasManager::class, function (Container $app): AtlasManager {
            return new AtlasManager(
                $app->make(AgentResolver::class),
                $app->make(AgentExecutorContract::class),
                $app->make(PipelineRunner::class),
            );
        });
    }

    /**
     * Register the discovery service.
     */
    protected function registerDiscoveryService(): void
    {
        $this->app->singleton(ClassDiscovery::class, function (): ClassDiscovery {
            return new ClassDiscovery;
        });
    }

    /**
     * Publish configuration files.
     */
    protected function publishConfig(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/atlas.php' => config_path('atlas.php'),
            ], 'atlas-config');
        }
    }

    /**
     * Define core pipelines for the package.
     */
    protected function defineCorePipelines(): void
    {
        $registry = $this->app->make(PipelineRegistry::class);

        // Agent pipelines
        foreach ([
            'agent.before_execute',
            'agent.context.validate',
            'agent.tools.merged',
            'agent.after_execute',
            'agent.stream.after',
            'agent.system_prompt.before_build',
            'agent.system_prompt.after_build',
            'agent.on_error',
        ] as $event) {
            $registry->define($event);
        }

        // Tool pipelines
        foreach ([
            'tool.before_resolve',
            'tool.after_resolve',
            'tool.before_execute',
            'tool.after_execute',
            'tool.on_error',
        ] as $event) {
            $registry->define($event);
        }

        // Prism pipelines (from PrismProxy configuration)
        foreach (PrismProxy::getPipelineEvents() as $event) {
            $registry->define($event);
        }
    }

    /**
     * Configure pipelines enabled state based on config.
     */
    protected function configurePipelinesState(): void
    {
        if (config('atlas.pipelines.enabled', true) === false) {
            $registry = $this->app->make(PipelineRegistry::class);

            // Disable all defined pipelines
            foreach ($registry->definitions() as $name => $definition) {
                $registry->setActive($name, false);
            }
        }
    }

    /**
     * Discover and register agents from configured path.
     */
    protected function discoverAgents(): void
    {
        $path = config('atlas.agents.path');
        $namespace = config('atlas.agents.namespace');

        if ($path === null || $path === '' || $namespace === null) {
            return;
        }

        $discovery = $this->app->make(ClassDiscovery::class);
        $registry = $this->app->make(AgentRegistryContract::class);

        $agents = $discovery->discover($path, $namespace, AgentContract::class);

        foreach ($agents as $agentClass) {
            $registry->register($agentClass);
        }
    }

    /**
     * Discover and register tools from configured path.
     */
    protected function discoverTools(): void
    {
        $path = config('atlas.tools.path');
        $namespace = config('atlas.tools.namespace');

        if ($path === null || $path === '' || $namespace === null) {
            return;
        }

        $discovery = $this->app->make(ClassDiscovery::class);
        $registry = $this->app->make(ToolRegistryContract::class);

        $tools = $discovery->discover($path, $namespace, ToolContract::class);

        foreach ($tools as $toolClass) {
            $registry->register($toolClass);
        }
    }
}
