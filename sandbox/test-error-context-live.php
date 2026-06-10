<?php

declare(strict_types=1);

/**
 * Error & Request Context Tracing — Live API Audit
 *
 * Validates, against the REAL provider APIs, that the observability additions
 * are wired end-to-end for every provider:
 *
 *   1. A successful call fires ProviderRequestStarted + ProviderRequestCompleted
 *      carrying the SAME correlationId, the right provider, and the right model.
 *   2. A bad-key call surfaces the provider's REAL error message
 *      (ProviderException::providerMessage), exposes the raw response body
 *      (responseBody()), and fires ProviderRequestFailed carrying the provider
 *      and a correlationId.
 *
 * Usage: php test-error-context-live.php
 * Requires OPENAI_API_KEY, ANTHROPIC_API_KEY, GEMINI_API_KEY, XAI_API_KEY in .env
 */

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Events\ProviderRequestCompleted;
use Atlasphp\Atlas\Events\ProviderRequestFailed;
use Atlasphp\Atlas\Events\ProviderRequestStarted;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Illuminate\Support\Facades\Facade;

require __DIR__.'/bootstrap.php'; // load env once

$providers = [
    'openai' => [
        'model' => 'gpt-4o-mini',
        'config' => ['api_key' => env('OPENAI_API_KEY'), 'url' => env('OPENAI_URL', 'https://api.openai.com/v1')],
    ],
    'anthropic' => [
        'model' => 'claude-sonnet-4-5-20250929',
        'config' => ['api_key' => env('ANTHROPIC_API_KEY'), 'url' => 'https://api.anthropic.com/v1', 'version' => '2023-06-01'],
    ],
    'google' => [
        'model' => 'gemini-2.5-flash',
        'config' => ['api_key' => env('GEMINI_API_KEY', env('GOOGLE_API_KEY')), 'url' => 'https://generativelanguage.googleapis.com'],
    ],
    'xai' => [
        'model' => 'grok-3-mini',
        'config' => ['api_key' => env('XAI_API_KEY'), 'url' => 'https://api.x.ai/v1'],
    ],
];

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;

    if ($ok) {
        $pass++;
        echo "  ✓ {$label}".($detail !== '' ? " — {$detail}" : '')."\n";
    } else {
        $fail++;
        echo "  ✗ {$label}".($detail !== '' ? " — {$detail}" : '')."\n";
    }
}

/**
 * Fresh container + registry so each phase resolves its own driver with its
 * own (good or bad) key, and the facade points at the new app.
 */
function freshApp(): mixed
{
    $app = require __DIR__.'/bootstrap.php';
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);

    return $app;
}

foreach ($providers as $key => $spec) {
    echo "\n=== {$key} ({$spec['model']}) ===\n";

    if (empty($spec['config']['api_key'])) {
        echo "  ⚠ no API key in .env — skipping\n";

        continue;
    }

    // ── 1. Successful call: correlation id stable + provider/model traced ──────
    $app = freshApp();
    $app['config']->set('atlas.providers', [$key => $spec['config']]);
    AtlasConfig::refresh();

    $events = [];
    $app['events']->listen(ProviderRequestStarted::class, function ($e) use (&$events): void {
        $events['started'] = $e;
    });
    $app['events']->listen(ProviderRequestCompleted::class, function ($e) use (&$events): void {
        $events['completed'] = $e;
    });

    try {
        $resp = Atlas::text($key, $spec['model'])->message('Reply with exactly: ok')->asText();

        check('successful call returned text', $resp->text !== '', '"'.trim($resp->text).'"');

        $started = $events['started'] ?? null;
        $completed = $events['completed'] ?? null;

        check('ProviderRequestStarted fired', $started !== null);
        check('ProviderRequestCompleted fired', $completed !== null);

        if ($started && $completed) {
            check('correlationId present', $started->correlationId !== null, $started->correlationId ?? 'null');
            check('correlationId stable start→complete', $started->correlationId === $completed->correlationId);
            check('provider traced', $started->provider === $key && $completed->provider === $key, $started->provider ?? 'null');
            check('model traced', $started->model === $spec['model'], $started->model ?? 'null');
        }
    } catch (Throwable $e) {
        check('successful call', false, get_class($e).': '.$e->getMessage());
    }

    // ── 2. Bad-key call: real provider message + raw body + failed-event context ─
    $app = freshApp();
    $badConfig = $spec['config'];
    $badConfig['api_key'] = 'sk-atlas-invalid-key-deadbeef';
    $app['config']->set('atlas.providers', [$key => $badConfig]);
    AtlasConfig::refresh();

    $failed = null;
    $app['events']->listen(ProviderRequestFailed::class, function ($e) use (&$failed): void {
        $failed = $e;
    });

    try {
        Atlas::text($key, $spec['model'])->message('hi')->withoutRetry()->asText();
        check('bad key throws', false, 'no exception thrown');
    } catch (ProviderException $e) {
        check('bad key throws ProviderException', true, get_class($e).' ['.$e->statusCode.']');
        check('providerMessage carries real reason', $e->providerMessage !== '', '"'.$e->providerMessage.'"');
        check('responseBody() exposes raw body', is_array($e->responseBody()) && $e->responseBody() !== []);
        check('exception provider matches', $e->provider === $key, $e->provider);
        check('ProviderRequestFailed fired', $failed !== null);

        if ($failed !== null) {
            check('failed-event provider traced', $failed->provider === $key, $failed->provider ?? 'null');
            check('failed-event correlationId present', $failed->correlationId !== null, $failed->correlationId ?? 'null');
        }
    } catch (Throwable $e) {
        check('bad key throws ProviderException', false, 'got '.get_class($e).': '.$e->getMessage());
    }
}

echo "\n────────────────────────────────────────\n";
echo "Context tracing audit: {$pass} passed, {$fail} failed\n";

exit($fail === 0 ? 0 : 1);
