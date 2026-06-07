<?php

declare(strict_types=1);

/**
 * Live prompt-caching check across all providers.
 *
 * Sends the same large-prefix request twice per provider and reports usage.
 * Anthropic needs explicit cache_control (Atlas adds it when cache is on);
 * OpenAI / xAI / Google cache automatically. Either way the second call should
 * report cached tokens.
 */

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Provider;

$app = require __DIR__.'/bootstrap.php';

// A stable, large system prefix (~2.5k tokens) — above every provider's cache
// minimum so the prefix is actually cacheable.
$big = '';
for ($i = 0; $i < 220; $i++) {
    $big .= "Rule {$i}: When reasoning about complex systems, prefer explicit, well-documented invariants over implicit assumptions, and always validate inputs at the boundary before trusting them downstream. ";
}

$cases = [
    ['Anthropic', Provider::Anthropic, 'claude-sonnet-4-5-20250929'],
    ['OpenAI', Provider::OpenAI, 'gpt-4o-mini'],
    ['xAI', Provider::xAI, 'grok-4-fast-non-reasoning'],
    ['Google', Provider::Google, 'gemini-2.5-flash'],
];

function usageLine(string $label, $r): string
{
    $u = $r->usage;
    if ($u === null) {
        return "   {$label}: <no usage>";
    }

    return sprintf(
        '   %s: input=%d cached=%s cacheWrite=%s output=%d',
        $label,
        $u->inputTokens,
        $u->cachedTokens === null ? '-' : (string) $u->cachedTokens,
        $u->cacheWriteTokens === null ? '-' : (string) $u->cacheWriteTokens,
        $u->outputTokens,
    );
}

foreach ($cases as [$name, $provider, $model]) {
    echo "\n── {$name} ({$model})\n";

    try {
        $first = Atlas::text($provider, $model)
            ->instructions($big)
            ->message('Reply with exactly: OK')
            ->asText();
        echo usageLine('call 1 (write)', $first)."\n";

        // Same prefix → should hit cache on the second call.
        $second = Atlas::text($provider, $model)
            ->instructions($big)
            ->message('Reply with exactly: OK')
            ->asText();
        echo usageLine('call 2 (read) ', $second)."\n";

        $cached = $second->usage?->cachedTokens ?? 0;
        $wrote = ($first->usage?->cacheWriteTokens ?? 0) > 0;
        $verdict = ($cached > 0 || $wrote) ? "✓ caching active (cached={$cached})" : '✗ no cache observed';
        echo "   {$verdict}\n";
    } catch (Throwable $e) {
        echo '   ERROR: '.$e->getMessage()."\n";
    }
}

echo "\n";
