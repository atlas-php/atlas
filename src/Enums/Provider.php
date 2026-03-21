<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Enums;

/**
 * Supported AI provider identifiers.
 */
enum Provider: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case Google = 'google';
    case xAI = 'xai';
    case ElevenLabs = 'elevenlabs';
}
