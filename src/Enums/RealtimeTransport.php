<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Enums;

/**
 * Transport mode for realtime voice-to-voice sessions.
 */
enum RealtimeTransport: string
{
    case WebRtc = 'webrtc';
    case WebSocket = 'websocket';
}
