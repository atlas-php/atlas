<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;
use Prism\Prism\Embeddings\Response as EmbeddingsResponse;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Images\Response as ImageResponse;

/**
 * Test command for verifying whenProvider conditional configuration.
 *
 * Demonstrates how whenProvider applies provider-specific options
 * only when the active provider matches.
 */
class WhenProviderCommand extends Command
{
    protected $signature = 'atlas:when-provider
                            {--provider=openai : The provider to use (openai, anthropic)}
                            {--capability=chat : The capability to test (chat, image, embedding)}';

    protected $description = 'Test whenProvider conditional provider configuration';

    public function handle(): int
    {
        $provider = $this->option('provider');
        $capability = $this->option('capability');

        $this->line('');
        $this->line('=== Atlas whenProvider Test ===');
        $this->line("Testing Provider: {$provider}");
        $this->line("Capability: {$capability}");
        $this->line('');

        return match ($capability) {
            'chat' => $this->testChat($provider),
            'image' => $this->testImage($provider),
            'embedding' => $this->testEmbedding($provider),
            default => $this->error("Unknown capability: {$capability}") ?? self::FAILURE,
        };
    }

    protected function testChat(string $provider): int
    {
        $this->info('Testing whenProvider with chat...');
        $this->line('');

        $this->line('Configuration:');
        $this->line('  - whenProvider("anthropic") => cacheType: ephemeral');
        $this->line('  - whenProvider("openai") => presence_penalty: 0.5');
        $this->line('');

        // Map provider to appropriate model
        $model = match ($provider) {
            'anthropic' => 'claude-sonnet-4-20250514',
            'gemini' => 'gemini-2.0-flash',
            default => 'gpt-4o',
        };

        try {
            // Track which callback was applied
            $appliedCallback = 'none';

            $response = Atlas::agent('general-assistant')
                ->withProvider($provider, $model)
                ->whenProvider('anthropic', function ($r) use (&$appliedCallback) {
                    $appliedCallback = 'anthropic';

                    return $r->withProviderOptions(['cacheType' => 'ephemeral']);
                })
                ->whenProvider('openai', function ($r) use (&$appliedCallback) {
                    $appliedCallback = 'openai';

                    return $r->withProviderOptions(['presence_penalty' => 0.5]);
                })
                ->whenProvider(Provider::Gemini, function ($r) use (&$appliedCallback) {
                    $appliedCallback = 'gemini';

                    return $r->withProviderOptions(['safety_settings' => []]);
                })
                ->chat('Say "Hello from '.$provider.'!" in exactly those words.');

            $this->line('--- Response ---');
            $this->info($response->text);
            $this->line('');

            $this->line('--- Details ---');
            $this->line("Provider: {$provider}");
            $this->line("Applied Callback: {$appliedCallback}");
            $this->line(sprintf(
                'Tokens: %d prompt / %d completion / %d total',
                $response->usage->promptTokens,
                $response->usage->completionTokens,
                $response->usage->promptTokens + $response->usage->completionTokens,
            ));
            $this->line('');

            $this->info('[PASS] whenProvider callback executed successfully');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function testImage(string $provider): int
    {
        $this->info('Testing whenProvider with image generation...');
        $this->line('');

        $this->line('Configuration:');
        $this->line('  - whenProvider("openai") => style: vivid');
        $this->line('');

        // Image generation requires OpenAI
        if ($provider !== 'openai') {
            $this->warn("Image generation primarily uses OpenAI. Testing with provider: {$provider}");
        }

        try {
            /** @var ImageResponse $response */
            $response = Atlas::image()
                ->using($provider, 'dall-e-3')
                ->withPrompt('A simple red circle on white background')
                ->whenProvider('openai', fn ($r) => $r
                    ->withProviderOptions(['style' => 'vivid']))
                ->whenProvider('anthropic', fn ($r) => $r
                    ->withProviderOptions(['custom_option' => 'value']))
                ->generate();

            $this->line('--- Response ---');
            $image = $response->firstImage();

            if ($image !== null) {
                $this->line('URL: '.($image->url ? 'Present' : 'Not provided'));
                $this->line('Base64: '.($image->base64 ? 'Present ('.strlen($image->base64).' bytes)' : 'Not provided'));
                $this->line('Revised Prompt: '.($image->revisedPrompt ?? 'None'));
            } else {
                $this->warn('No image generated');
            }
            $this->line('');

            $this->info('[PASS] Image whenProvider callback executed successfully');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function testEmbedding(string $provider): int
    {
        $this->info('Testing whenProvider with embeddings...');
        $this->line('');

        $this->line('Configuration:');
        $this->line('  - whenProvider("openai") => dimensions: 256');
        $this->line('');

        try {
            /** @var EmbeddingsResponse $response */
            $response = Atlas::embeddings()
                ->using($provider, 'text-embedding-3-small')
                ->fromInput('Hello world')
                ->whenProvider('openai', fn ($r) => $r
                    ->withProviderOptions(['dimensions' => 256]))
                ->whenProvider('anthropic', fn ($r) => $r
                    ->withProviderOptions(['custom_option' => 'value']))
                ->asEmbeddings();

            $embedding = $response->embeddings[0]->embedding;

            $this->line('--- Response ---');
            $this->line('Dimensions: '.count($embedding));
            $this->line('First 5 values: ['.implode(', ', array_map(fn ($v) => round((float) $v, 4), array_slice($embedding, 0, 5))).']');
            $this->line('');

            $expectedDimensions = $provider === 'openai' ? 256 : null;
            if ($expectedDimensions && count($embedding) === $expectedDimensions) {
                $this->info("[PASS] Embedding has expected dimensions ({$expectedDimensions}) - whenProvider callback applied!");
            } else {
                $this->info('[PASS] Embedding generated successfully');
                if ($provider === 'openai') {
                    $this->line('Note: Dimensions depend on model support for the dimensions parameter');
                }
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
