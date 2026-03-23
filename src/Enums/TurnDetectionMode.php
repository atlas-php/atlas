<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Enums;

/**
 * Turn detection mode for voice sessions.
 */
enum TurnDetectionMode: string
{
    case ServerVad = 'server_vad';
    case Manual = 'manual';
}
