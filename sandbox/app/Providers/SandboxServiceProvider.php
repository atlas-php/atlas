<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\ChatCommand;
use App\Console\Commands\EmbedCommand;
use App\Console\Commands\ImageCommand;
use App\Console\Commands\LocalChatCommand;
use App\Console\Commands\ModerationCommand;
use App\Console\Commands\PackagistTestCommand;
use App\Console\Commands\ProviderResolutionCommand;
use App\Console\Commands\SpeechCommand;
use App\Console\Commands\StreamCommand;
use App\Console\Commands\StructuredCommand;
use App\Console\Commands\ToolsCommand;
use App\Console\Commands\VisionCommand;
use App\Console\Commands\WhenProviderCommand;
use App\Services\ThreadStorageService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Atlas sandbox environment.
 *
 * Registers sandbox-specific commands and services for testing
 * Atlas functionality against real AI providers.
 *
 * Agents and tools are auto-discovered from app/Agents and app/Tools
 * directories via the atlas.php configuration.
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
                ModerationCommand::class,
                PackagistTestCommand::class,
                ProviderResolutionCommand::class,
                SpeechCommand::class,
                StreamCommand::class,
                StructuredCommand::class,
                ToolsCommand::class,
                VisionCommand::class,
                WhenProviderCommand::class,
            ]);
        }
    }
}
