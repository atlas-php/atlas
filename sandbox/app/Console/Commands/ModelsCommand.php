<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Models\Enums\ProviderEndpoint;
use Illuminate\Console\Command;

/**
 * List available models from AI providers.
 *
 * Fetches and displays models from provider APIs with caching support.
 */
class ModelsCommand extends Command
{
    protected $signature = 'atlas:models
        {provider? : The provider to list models for (e.g., openai, anthropic)}
        {--all : List models from all configured providers}
        {--refresh : Force refresh from the API (bypass cache)}
        {--clear : Clear cached models for the provider}';

    protected $description = 'List available models from AI providers';

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->listAllProviders();
        }

        $provider = $this->argument('provider') ?? $this->promptForProvider();

        if ($provider === null) {
            return self::FAILURE;
        }

        if ($this->option('clear')) {
            Atlas::models($provider)->clear();
            $this->info("Cache cleared for: {$provider}");

            return self::SUCCESS;
        }

        return $this->listProvider($provider);
    }

    /**
     * List models for a single provider.
     */
    protected function listProvider(string $provider): int
    {
        if (! Atlas::models($provider)->has()) {
            $this->warn("Provider '{$provider}' does not have a models listing endpoint.");

            return self::FAILURE;
        }

        $this->info("Fetching models for: {$provider}");

        $models = $this->option('refresh')
            ? Atlas::models($provider)->refresh()
            : Atlas::models($provider)->all();

        if ($models === null) {
            $this->error("Failed to fetch models for '{$provider}'. Check your API key and configuration.");

            return self::FAILURE;
        }

        if ($models === []) {
            $this->warn('No models found.');

            return self::SUCCESS;
        }

        $this->displayModels($models, $provider);

        return self::SUCCESS;
    }

    /**
     * List models from all configured providers.
     */
    protected function listAllProviders(): int
    {
        $this->info('Fetching models from all configured providers...');
        $this->line('');

        $rows = [];
        $providerCount = 0;

        foreach (ProviderEndpoint::cases() as $endpoint) {
            if (! $endpoint->hasModelsEndpoint()) {
                continue;
            }

            $models = $this->option('refresh')
                ? Atlas::models($endpoint->value)->refresh()
                : Atlas::models($endpoint->value)->all();

            if ($models === null) {
                continue;
            }

            $providerCount++;

            foreach ($models as $model) {
                $rows[] = [
                    $model['id'],
                    $model['name'] ?? '-',
                    $endpoint->value,
                ];
            }
        }

        if ($rows === []) {
            $this->warn('No models found from any provider. Check your API keys.');

            return self::FAILURE;
        }

        $this->table(['ID', 'Name', 'Provider'], $rows);
        $this->line('');
        $this->info('Total: '.count($rows).' models from '.$providerCount.' providers');

        return self::SUCCESS;
    }

    /**
     * Prompt user to select a provider.
     */
    protected function promptForProvider(): ?string
    {
        $providers = [];

        foreach (ProviderEndpoint::cases() as $endpoint) {
            if ($endpoint->hasModelsEndpoint()) {
                $providers[] = $endpoint->value;
            }
        }

        return $this->choice('Select a provider', $providers);
    }

    /**
     * Display models in a table.
     *
     * @param  list<array{id: string, name: string|null}>  $models
     */
    protected function displayModels(array $models, string $provider): void
    {
        $rows = array_map(
            fn (array $model): array => [
                $model['id'],
                $model['name'] ?? '-',
                $provider,
            ],
            $models,
        );

        $this->table(['ID', 'Name', 'Provider'], $rows);
        $this->line('');
        $this->info('Total: '.count($models).' models');
    }
}
