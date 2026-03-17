<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Models\Enums;

/**
 * Defines the JSON response formats returned by provider model listing endpoints.
 *
 * Each case maps to a distinct JSON structure that requires its own parsing logic
 * in ModelResponseParser.
 */
enum ResponseFormat: string
{
    /** OpenAI-compatible format: data[].id (OpenAI, Groq, DeepSeek, Mistral, XAI, OpenRouter) */
    case OpenAiCompatible = 'openai_compatible';

    /** Anthropic format: data[].id + data[].display_name */
    case Anthropic = 'anthropic';

    /** Gemini format: models[].name (strip models/ prefix) + models[].displayName */
    case Gemini = 'gemini';

    /** Ollama native format: models[].name (from /api/tags endpoint) */
    case ModelsArray = 'models_array';

    /** ElevenLabs format: flat array [].model_id + [].name */
    case ElevenLabs = 'elevenlabs';
}
