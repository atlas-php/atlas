<?php

declare(strict_types=1);

/**
 * Sandbox integration test for realtime sessions.
 *
 * Usage: cd sandbox && php test-voice.php
 *
 * Tests session creation for OpenAI (WebRTC and WebSocket modes).
 * Requires OPENAI_API_KEY in .env.
 */

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Atlasphp\Atlas\Atlas;
use Illuminate\Contracts\Console\Kernel;

echo "═══ Atlas Realtime Session Tests ═══\n\n";

// ─── Test 1: OpenAI WebRTC Session ──────────────────────────
echo "1. Creating OpenAI WebRTC session...\n";
try {
    $session = Atlas::realtime('openai', 'gpt-4o-realtime-preview-2024-12-17')
        ->instructions('You are a helpful assistant.')
        ->withVoice('alloy')
        ->viaWebRtc()
        ->createSession();

    echo "   ✓ Session ID: {$session->sessionId}\n";
    echo "   ✓ Transport: {$session->transport->value}\n";
    echo '   ✓ Has ephemeral token: '.($session->ephemeralToken ? 'yes' : 'no')."\n";
    echo '   ✓ Expires: '.($session->expiresAt?->format('Y-m-d H:i:s') ?? 'n/a')."\n\n";
} catch (Throwable $e) {
    echo "   ✗ Failed: {$e->getMessage()}\n\n";
}

// ─── Test 2: OpenAI WebSocket Session ───────────────────────
echo "2. Creating OpenAI WebSocket session...\n";
try {
    $session = Atlas::realtime('openai', 'gpt-4o-realtime-preview-2024-12-17')
        ->instructions('You are a helpful assistant.')
        ->viaWebSocket()
        ->createSession();

    echo "   ✓ Session ID: {$session->sessionId}\n";
    echo "   ✓ Transport: {$session->transport->value}\n";
    echo "   ✓ Connection URL: {$session->connectionUrl}\n\n";
} catch (Throwable $e) {
    echo "   ✗ Failed: {$e->getMessage()}\n\n";
}

// ─── Test 3: xAI Session ────────────────────────────────────
echo "3. Creating xAI realtime session...\n";
try {
    $session = Atlas::realtime('xai', 'grok-2-realtime')
        ->instructions('You are a helpful assistant.')
        ->createSession();

    echo "   ✓ Session ID: {$session->sessionId}\n";
    echo "   ✓ Transport: {$session->transport->value}\n";
    echo "   ✓ Connection URL: {$session->connectionUrl}\n\n";
} catch (Throwable $e) {
    echo "   ✗ Failed: {$e->getMessage()}\n\n";
}

echo "═══ Done ═══\n";
