<?php

declare(strict_types=1);

/**
 * Provider-native tools — live API test (typed ProviderTool classes).
 *
 * Exercises the WebSearch/WebFetch tools through each provider's ToolMapper
 * against the real API: domain scoping (allowed_domains), the options merge bag,
 * and that provider-executed tools are captured as providerToolCalls/annotations
 * rather than entering the client tool loop.
 *
 * Usage: php test-provider-tools-live.php
 * Requires OPENAI_API_KEY / ANTHROPIC_API_KEY / XAI_API_KEY in sandbox/.env
 */
$app = require __DIR__.'/bootstrap.php';

$app['config']->set('atlas.providers', [
    'openai' => ['api_key' => env('OPENAI_API_KEY'), 'url' => env('OPENAI_URL', 'https://api.openai.com/v1')],
    'anthropic' => ['api_key' => env('ANTHROPIC_API_KEY'), 'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1')],
    'xai' => ['api_key' => env('XAI_API_KEY'), 'url' => env('XAI_URL', 'https://api.x.ai/v1')],
]);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Providers\Tools\WebFetch;
use Atlasphp\Atlas\Providers\Tools\WebSearch;

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo '  '.($ok ? '✓' : '✗')."  {$label}".($detail !== '' ? "  — {$detail}" : '')."\n";
}

/**
 * @param  array<int, ProviderTool>  $tools
 */
function runWebSearch(Provider $provider, string $model, array $tools, string $prompt): void
{
    $label = $provider->value;
    try {
        $r = Atlas::text($provider, $model)
            ->instructions('Use the available web tools to answer. Be brief.')
            ->message($prompt)
            ->withProviderTools($tools)
            ->asText();

        check("{$label}: returned text", $r->text !== '', mb_substr($r->text, 0, 70));
        check(
            "{$label}: provider tool executed (providerToolCalls or annotations captured)",
            $r->providerToolCalls !== [] || $r->annotations !== [],
            'calls='.count($r->providerToolCalls).' annotations='.count($r->annotations),
        );
        check("{$label}: no client tool calls leaked from server tools", $r->toolCalls === []);
    } catch (Throwable $e) {
        check("{$label}: web search live call", false, get_class($e).': '.$e->getMessage());
    }
}

echo "── OpenAI (web_search + allowed_domains via filters)\n";
runWebSearch(Provider::OpenAI, 'gpt-4o', [new WebSearch(allowedDomains: ['php.net'])], 'What is the latest PHP version? Search php.net.');

echo "\n── Anthropic (web_search_20250305 + allowed_domains, top-level)\n";
runWebSearch(Provider::Anthropic, 'claude-sonnet-4-5', [new WebSearch(allowedDomains: ['php.net'], options: ['max_uses' => 3])], 'What is the latest PHP version? Search php.net.');

echo "\n── Anthropic (web_fetch_20250910)\n";
runWebSearch(Provider::Anthropic, 'claude-sonnet-4-5', [new WebFetch(['max_uses' => 2])], 'Fetch https://www.php.net and tell me the current stable PHP version shown.');

echo "\n── xAI (web_search + allowed_domains via filters)\n";
runWebSearch(Provider::xAI, 'grok-3', [new WebSearch(allowedDomains: ['php.net'])], 'What is the latest PHP version? Search php.net.');

echo "\n══════════════════════════════════════════════\n";
echo "  Results: {$pass} passed, {$fail} failed\n";
echo "══════════════════════════════════════════════\n";
exit($fail === 0 ? 0 : 1);
