<?php

declare(strict_types=1);

/**
 * Token Counting Live API Test
 *
 * Validates Atlas::text(...)->countTokens() against the real provider endpoints:
 *   - Anthropic  POST /v1/messages/count_tokens   (native)
 *   - OpenAI     POST /v1/responses/input_tokens   (native)
 *   - Google     POST .../{model}:countTokens      (native)
 *   - xAI        heuristic estimate (no full-request count endpoint)
 *
 * For each native provider it also runs a real generation and compares the
 * pre-flight count to the post-call usage->inputTokens to prove accuracy, and
 * exercises multimodal (image) + tool/system counting that no local tokenizer
 * can do.
 *
 * Usage: php test-token-counting.php
 * Requires ANTHROPIC_API_KEY, OPENAI_API_KEY, GEMINI_API_KEY, XAI_API_KEY in sandbox/.env
 */
$app = require __DIR__.'/bootstrap.php';

$app['config']->set('atlas.providers', [
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
    ],
    'google' => [
        'api_key' => env('GEMINI_API_KEY', env('GOOGLE_API_KEY')),
        'url' => env('GOOGLE_URL', 'https://generativelanguage.googleapis.com'),
    ],
    'xai' => [
        'api_key' => env('XAI_API_KEY'),
        'url' => env('XAI_URL', 'https://api.x.ai/v1'),
    ],
]);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Tools\Tool;

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "\n  ✓ {$name}".($detail !== '' ? "  ({$detail})" : '');
    } else {
        $failed++;
        echo "\n  ✗ {$name}".($detail !== '' ? "  ({$detail})" : '');
    }
}

// A simple client tool — its name + description add tokens to the request,
// which the native count endpoints include and a text-only count does not.
$weatherTool = new class extends Tool
{
    public function name(): string
    {
        return 'get_current_weather';
    }

    public function description(): string
    {
        return 'Get the current weather conditions, temperature, humidity, and wind '
            .'for a specific city and country. Use this whenever the user asks about weather.';
    }

    public function handle(array $args, array $context): mixed
    {
        return 'sunny';
    }
};

$logo = __DIR__.'/public/atlas-logo.png';
$longSystem = str_repeat('You are a precise, terse assistant. ', 40);

echo '╔══════════════════════════════════════════════╗';
echo "\n║   Token Counting Live API Tests              ║";
echo "\n╚══════════════════════════════════════════════╝";

/**
 * Run the standard battery for one provider.
 */
function battery(string $label, Provider $provider, string $model, bool $native, bool $vision, $tool, string $logo, string $longSystem): void
{
    echo "\n\n── {$label} ({$model})";

    try {
        // 1) Plain text count
        $text = Atlas::text($provider, $model)
            ->message('What is the capital of France? Answer in one word.')
            ->countTokens();

        check("{$label}: text count > 0", $text->inputTokens > 0, "input={$text->inputTokens}");
        check("{$label}: estimated flag", $text->estimated === ! $native, $native ? 'native (estimated=false)' : 'heuristic (estimated=true)');
        check("{$label}: provider attributed", $text->provider !== '', $text->provider);

        // 2) System prompt + tool should raise the count above text-only
        $withTool = Atlas::text($provider, $model)
            ->instructions($longSystem)
            ->message('What is the capital of France? Answer in one word.')
            ->withTools([$tool])
            ->countTokens();

        check("{$label}: system+tool raises count", $withTool->inputTokens > $text->inputTokens,
            "text={$text->inputTokens} → +sys+tool={$withTool->inputTokens}");

        // 3) Native accuracy: compare pre-flight count to real usage->inputTokens
        if ($native) {
            $resp = Atlas::text($provider, $model)
                ->message('What is the capital of France? Answer in one word.')
                ->asText();

            $delta = abs($resp->usage->inputTokens - $text->inputTokens);
            // Counting endpoints estimate within a few tokens of the billed input.
            check("{$label}: count ≈ usage->inputTokens", $delta <= 5,
                "count={$text->inputTokens} usage={$resp->usage->inputTokens} Δ={$delta}");
        }

        // 4) Multimodal: an image must add a large number of tokens
        if ($vision) {
            $withImage = Atlas::text($provider, $model)
                ->message('Describe this image.', Image::fromPath($logo, 'image/png'))
                ->countTokens();

            check("{$label}: image adds tokens (multimodal)", $withImage->inputTokens > $text->inputTokens,
                "text={$text->inputTokens} → +image={$withImage->inputTokens}");
        }
    } catch (Throwable $e) {
        check("{$label}: battery completed", false, $e->getMessage());
    }
}

battery('Anthropic', Provider::Anthropic, 'claude-sonnet-4-5-20250929', native: true, vision: true, tool: $weatherTool, logo: $logo, longSystem: $longSystem);
battery('OpenAI', Provider::OpenAI, 'gpt-4o', native: true, vision: true, tool: $weatherTool, logo: $logo, longSystem: $longSystem);
battery('Google', Provider::Google, 'gemini-2.5-flash', native: true, vision: true, tool: $weatherTool, logo: $logo, longSystem: $longSystem);
battery('xAI', Provider::xAI, 'grok-3', native: false, vision: false, tool: $weatherTool, logo: $logo, longSystem: $longSystem);

echo "\n\n──────────────────────────────────────────────";
echo "\n  Passed: {$passed}   Failed: {$failed}";
echo "\n──────────────────────────────────────────────\n";

exit($failed === 0 ? 0 : 1);
