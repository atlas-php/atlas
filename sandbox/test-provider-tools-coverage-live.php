<?php

declare(strict_types=1);

/**
 * Provider-tools coverage — live verification of EVERY registry entry.
 *
 * Confirms each (provider, tool) pair in ProviderToolRegistry actually works
 * against the real API. file_search is excluded — it needs a pre-built vector
 * store / collection and is verified separately.
 *
 * Usage: php test-provider-tools-coverage-live.php
 */
$app = require __DIR__.'/bootstrap.php';

$app['config']->set('atlas.providers', [
    'openai' => ['api_key' => env('OPENAI_API_KEY'), 'url' => env('OPENAI_URL', 'https://api.openai.com/v1')],
    'anthropic' => ['api_key' => env('ANTHROPIC_API_KEY'), 'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1')],
    'google' => ['api_key' => env('GEMINI_API_KEY'), 'url' => env('GOOGLE_URL', 'https://generativelanguage.googleapis.com/v1beta')],
    'xai' => ['api_key' => env('XAI_API_KEY'), 'url' => env('XAI_URL', 'https://api.x.ai/v1')],
]);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Providers\Tools\CodeExecution;
use Atlasphp\Atlas\Providers\Tools\CodeInterpreter;
use Atlasphp\Atlas\Providers\Tools\GoogleSearch;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Providers\Tools\WebFetch;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Providers\Tools\XSearch;

$pass = 0;
$fail = 0;

/**
 * @param  array<int, ProviderTool>  $tools
 */
function probe(string $label, Provider $provider, string $model, array $tools, string $prompt, string $expectInText = ''): void
{
    global $pass, $fail;
    try {
        $r = Atlas::text($provider, $model)
            ->instructions('Use the available provider tools to answer. Be brief.')
            ->message($prompt)
            ->withProviderTools($tools)
            ->asText();

        $textOk = $expectInText === '' ? ($r->text !== '') : str_contains($r->text, $expectInText);
        $evidence = 'toolCalls='.count($r->providerToolCalls).' annotations='.count($r->annotations);
        $ok = $textOk;
        $ok ? $pass++ : $fail++;
        echo '  '.($ok ? '✓' : '✗')."  {$label}  — ".trim(mb_substr($r->text, 0, 60))."  [{$evidence}]\n";
    } catch (Throwable $e) {
        $fail++;
        echo "  ✗  {$label}  — ".get_class($e).': '.trim(mb_substr($e->getMessage(), 0, 140))."\n";
    }
}

echo "── OpenAI\n";
probe('openai web_search', Provider::OpenAI, 'gpt-4o', [new WebSearch(allowedDomains: ['php.net'])], 'What is the latest PHP version? Search php.net.');
probe('openai code_interpreter', Provider::OpenAI, 'gpt-4o', [new CodeInterpreter], 'Use code to compute 47 * 53. Reply with just the number.', '2491');

echo "\n── Anthropic\n";
probe('anthropic web_search', Provider::Anthropic, 'claude-sonnet-4-5', [new WebSearch(allowedDomains: ['php.net'], options: ['max_uses' => 3])], 'What is the latest PHP version? Search php.net.');
probe('anthropic web_fetch', Provider::Anthropic, 'claude-sonnet-4-5', [new WebFetch(['max_uses' => 2])], 'Fetch https://www.php.net and tell me the current stable PHP version shown.');

echo "\n── Google\n";
probe('google google_search', Provider::Google, 'gemini-2.5-flash', [new GoogleSearch], 'What is the latest PHP version?');
probe('google code_execution', Provider::Google, 'gemini-2.5-flash', [new CodeExecution], 'Use code to compute 47 * 53. Reply with just the number.', '2491');

echo "\n── xAI\n";
probe('xai web_search', Provider::xAI, 'grok-3', [new WebSearch(allowedDomains: ['php.net'])], 'What is the latest PHP version? Search php.net.');
probe('xai x_search', Provider::xAI, 'grok-3', [new XSearch], 'Search X for the most recent post from @php and summarize it.');
probe('xai code_interpreter', Provider::xAI, 'grok-4', [new CodeInterpreter], 'Use code to compute 47 * 53. Reply with just the number.', '2491');

echo "\n══════════════════════════════════════════════\n";
echo "  Results: {$pass} passed, {$fail} failed\n";
echo "══════════════════════════════════════════════\n";
exit($fail === 0 ? 0 : 1);
