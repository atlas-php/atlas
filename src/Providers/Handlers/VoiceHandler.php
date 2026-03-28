<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Providers\WebSocketConnection;
use Atlasphp\Atlas\Requests\VoiceRequest;
use Atlasphp\Atlas\Responses\VoiceSession;

/**
 * Handler for voice-to-voice sessions.
 *
 * createSession() is an HTTP call to get tokens/config.
 * connect() opens a persistent WebSocket connection.
 */
interface VoiceHandler
{
    public function createSession(VoiceRequest $request): VoiceSession;

    public function connect(VoiceSession $session): WebSocketConnection;
}
