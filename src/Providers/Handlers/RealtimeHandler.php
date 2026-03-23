<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Providers\WebSocketConnection;
use Atlasphp\Atlas\Requests\RealtimeRequest;
use Atlasphp\Atlas\Responses\RealtimeSession;

/**
 * Handler for realtime voice-to-voice sessions.
 *
 * createSession() is an HTTP call to get tokens/config.
 * connect() opens a persistent WebSocket connection.
 */
interface RealtimeHandler
{
    public function createSession(RealtimeRequest $request): RealtimeSession;

    public function connect(RealtimeSession $session): WebSocketConnection;
}
