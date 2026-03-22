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

    /**
     * Normalize a Provider enum or string to a string key.
     */
    public static function normalize(self|string $provider): string
    {
        return $provider instanceof self ? $provider->value : (string) $provider;
    }
}
