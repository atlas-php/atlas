<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Enums;

/**
 * Transport protocol for browser-direct voice sessions.
 */
enum VoiceTransport: string
{
    case WebRtc = 'webrtc';
    case WebSocket = 'websocket';
}
