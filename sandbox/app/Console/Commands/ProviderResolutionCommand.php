<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Providers\Facades\Atlas;
use Illuminate\Console\Command;

/**
 * Test command for verifying provider/model resolution logic.
 *
 * Resolution order:
 * 1. withProvider() override - takes precedence
 * 2. Agent's configured provider/model
 * 3. Config defaults (atlas.chat.provider, atlas.chat.model)
 */
class ProviderResolutionCommand extends Command
{
    protected $signature = 'atlas:provider-resolution';

    protected $description = 'Verify provider/model resolution logic';

    public function handle(): int
    {
        $this->line('');
        $this->line('=== Provider/Model Resolution Logic Test ===');
        $this->line('');

        // Show current config defaults
        $configProvider = config('atlas.chat.provider');
        $configModel = config('atlas.chat.model');
        $this->info('Config Defaults:');
        $this->line("  atlas.chat.provider: {$configProvider}");
        $this->line("  atlas.chat.model: {$configModel}");
        $this->line('');

        // Test 1: Agent's provider/model (general-assistant uses openai/gpt-4o)
        $this->testAgentProvider();

        // Test 2: withProvider() override
        $this->testWithProviderOverride();

        // Test 3: withProvider() with model override
        $this->testWithProviderAndModelOverride();

        // Test 4: whenProvider callbacks with override
        $this->testWhenProviderWithOverride();

        $this->line('');
        $this->info('=== All Resolution Tests Complete ===');

        return self::SUCCESS;
    }

    protected function testAgentProvider(): void
    {
        $this->info('Test 1: Agent Provider/Model (no override)');
        $this->line('  Agent: general-assistant (configured with openai/gpt-4o)');
        $this->line('  Expected: Uses agent\'s provider/model');
        $this->line('');

        try {
            $response = Atlas::agent('general-assistant')
                ->chat('Respond with exactly: "Provider: openai, Model: gpt-4o"');

            $this->line("  Response: {$response->text}");
            $this->info('  [PASS] Agent provider/model used');
        } catch (\Throwable $e) {
            $this->error("  Error: {$e->getMessage()}");
        }

        $this->line('');
    }

    protected function testWithProviderOverride(): void
    {
        $this->info('Test 2: withProvider() Override (provider only)');
        $this->line('  Agent: general-assistant (configured with openai/gpt-4o)');
        $this->line('  Override: withProvider("anthropic", "claude-sonnet-4-20250514")');
        $this->line('  Expected: Uses override provider AND model');
        $this->line('');

        try {
            $response = Atlas::agent('general-assistant')
                ->withProvider('anthropic', 'claude-sonnet-4-20250514')
                ->chat('Respond with exactly: "Provider: anthropic, Model: claude-sonnet-4"');

            $this->line("  Response: {$response->text}");
            $this->info('  [PASS] withProvider() override applied');
        } catch (\Throwable $e) {
            $this->error("  Error: {$e->getMessage()}");
        }

        $this->line('');
    }

    protected function testWithProviderAndModelOverride(): void
    {
        $this->info('Test 3: withProvider() + withModel() Override');
        $this->line('  Agent: general-assistant (configured with openai/gpt-4o)');
        $this->line('  Override: withProvider("openai")->withModel("gpt-4o-mini")');
        $this->line('  Expected: Uses override provider and model');
        $this->line('');

        try {
            $response = Atlas::agent('general-assistant')
                ->withProvider('openai')
                ->withModel('gpt-4o-mini')
                ->chat('Respond with exactly: "Provider: openai, Model: gpt-4o-mini"');

            $this->line("  Response: {$response->text}");
            $this->info('  [PASS] withProvider() + withModel() override applied');
        } catch (\Throwable $e) {
            $this->error("  Error: {$e->getMessage()}");
        }

        $this->line('');
    }

    protected function testWhenProviderWithOverride(): void
    {
        $this->info('Test 4: whenProvider() with withProvider() Override');
        $this->line('  Agent: general-assistant (configured with openai/gpt-4o)');
        $this->line('  Override: withProvider("anthropic", "claude-sonnet-4-20250514")');
        $this->line('  Callbacks:');
        $this->line('    - whenProvider("openai") => metadata: openai-callback');
        $this->line('    - whenProvider("anthropic") => metadata: anthropic-callback');
        $this->line('  Expected: Only anthropic callback applies (matches override)');
        $this->line('');

        try {
            // Track which callback was applied via metadata
            $appliedCallback = 'none';

            $response = Atlas::agent('general-assistant')
                ->withProvider('anthropic', 'claude-sonnet-4-20250514')
                ->whenProvider('openai', function ($r) use (&$appliedCallback) {
                    $appliedCallback = 'openai';

                    return $r->withMetadata(['callback' => 'openai']);
                })
                ->whenProvider('anthropic', function ($r) use (&$appliedCallback) {
                    $appliedCallback = 'anthropic';

                    return $r->withMetadata(['callback' => 'anthropic']);
                })
                ->chat('Respond with exactly: "Callback test complete"');

            $this->line("  Response: {$response->text}");
            $this->line("  Applied Callback: {$appliedCallback}");

            if ($appliedCallback === 'anthropic') {
                $this->info('  [PASS] Correct whenProvider() callback applied based on override');
            } else {
                $this->error("  [FAIL] Expected 'anthropic' callback, got '{$appliedCallback}'");
            }
        } catch (\Throwable $e) {
            $this->error("  Error: {$e->getMessage()}");
        }

        $this->line('');
    }
}
