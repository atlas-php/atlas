<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\ChatCommand;
use App\Console\Commands\EmbedCommand;
use App\Console\Commands\ImageCommand;
use App\Console\Commands\LocalChatCommand;
use App\Console\Commands\SpeechCommand;
use App\Console\Commands\StructuredCommand;
use App\Console\Commands\ToolsCommand;
use App\Console\Commands\VisionCommand;
use App\Services\Agents\AnthropicAssistantAgent;
use App\Services\Agents\AnthropicToolDemoAgent;
use App\Services\Agents\AnthropicVisionAgent;
use App\Services\Agents\GeminiAssistantAgent;
use App\Services\Agents\GeminiToolDemoAgent;
use App\Services\Agents\GeminiVisionAgent;
use App\Services\Agents\GeneralAssistantAgent;
use App\Services\Agents\LocalLMAgent;
use App\Services\Agents\OpenAIVisionAgent;
use App\Services\Agents\OpenAIWebSearchAgent;
use App\Services\Agents\StructuredOutputAgent;
use App\Services\Agents\ToolDemoAgent;
use App\Services\Agents\XAIAssistantAgent;
use App\Services\ThreadStorageService;
use App\Services\Tools\CalculatorTool;
use App\Services\Tools\DateTimeTool;
use App\Services\Tools\WeatherTool;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Atlas sandbox environment.
 *
 * Registers sandbox-specific commands, agents, and tools for testing
 * Atlas functionality against real AI providers.
 */
class SandboxServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ThreadStorageService::class, function () {
            return new ThreadStorageService(
                dirname(__DIR__, 2).'/storage/threads'
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerAgents();
        $this->registerTools();
    }

    /**
     * Register sandbox console commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ChatCommand::class,
                EmbedCommand::class,
                ImageCommand::class,
                LocalChatCommand::class,
                SpeechCommand::class,
                StructuredCommand::class,
                ToolsCommand::class,
                VisionCommand::class,
            ]);
        }
    }

    /**
     * Register sandbox agents.
     */
    protected function registerAgents(): void
    {
        /** @var AgentRegistryContract $registry */
        $registry = $this->app->make(AgentRegistryContract::class);

        $registry->register(GeneralAssistantAgent::class);
        $registry->register(LocalLMAgent::class);
        $registry->register(ToolDemoAgent::class);
        $registry->register(StructuredOutputAgent::class);
        $registry->register(AnthropicAssistantAgent::class);
        $registry->register(AnthropicToolDemoAgent::class);
        $registry->register(GeminiAssistantAgent::class);
        $registry->register(GeminiToolDemoAgent::class);
        $registry->register(OpenAIWebSearchAgent::class);
        $registry->register(OpenAIVisionAgent::class);
        $registry->register(AnthropicVisionAgent::class);
        $registry->register(GeminiVisionAgent::class);
        $registry->register(XAIAssistantAgent::class);
    }

    /**
     * Register sandbox tools.
     */
    protected function registerTools(): void
    {
        /** @var ToolRegistryContract $registry */
        $registry = $this->app->make(ToolRegistryContract::class);

        $registry->register(CalculatorTool::class);
        $registry->register(WeatherTool::class);
        $registry->register(DateTimeTool::class);
    }
}
