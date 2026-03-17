<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Models\Enums;

/**
 * Maps AI providers to their model listing endpoint configuration.
 *
 * Each case corresponds to a Prism Provider enum value and knows how to
 * construct the full URL, headers, and determine the response format
 * for fetching available models from that provider's API.
 */
enum ProviderEndpoint: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case Ollama = 'ollama';
    case DeepSeek = 'deepseek';
    case Mistral = 'mistral';
    case Groq = 'groq';
    case XAI = 'xai';
    case OpenRouter = 'openrouter';
    case Perplexity = 'perplexity';
    case VoyageAI = 'voyageai';
    case ElevenLabs = 'elevenlabs';
    case Z = 'z';

    /**
     * Whether this provider has a models listing endpoint.
     */
    public function hasModelsEndpoint(): bool
    {
        return match ($this) {
            self::Perplexity, self::VoyageAI, self::Z => false,
            default => true,
        };
    }

    /**
     * The relative endpoint path for listing models.
     */
    public function endpointPath(): ?string
    {
        return match ($this) {
            self::Gemini => null,
            self::Ollama => '/v1/models',
            self::ElevenLabs => '/models',
            self::Perplexity, self::VoyageAI, self::Z => null,
            default => '/models',
        };
    }

    /**
     * The response format parser to use for this provider.
     */
    public function responseFormat(): ?ResponseFormat
    {
        return match ($this) {
            self::Anthropic => ResponseFormat::Anthropic,
            self::Gemini => ResponseFormat::Gemini,
            self::ElevenLabs => ResponseFormat::ElevenLabs,
            self::Perplexity, self::VoyageAI, self::Z => null,
            default => ResponseFormat::OpenAiCompatible,
        };
    }

    /**
     * Build authentication headers for the provider.
     *
     * @param  string  $apiKey  The API key for the provider.
     * @return array<string, string>
     */
    public function buildHeaders(string $apiKey): array
    {
        return match ($this) {
            self::Anthropic => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            self::ElevenLabs => [
                'xi-api-key' => $apiKey,
            ],
            self::Gemini => [],
            default => [
                'Authorization' => "Bearer {$apiKey}",
            ],
        };
    }

    /**
     * Build the full URL for the models listing endpoint.
     *
     * @param  string  $baseUrl  The base URL from Prism config.
     * @param  string  $apiKey  The API key (used as query param for Gemini).
     */
    public function buildUrl(string $baseUrl, string $apiKey = ''): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        if ($this === self::Gemini) {
            return "{$baseUrl}?key={$apiKey}";
        }

        $path = $this->endpointPath();

        return $path !== null ? "{$baseUrl}{$path}" : $baseUrl;
    }

    /**
     * Whether this provider can operate without an API key.
     */
    public function isKeyless(): bool
    {
        return $this === self::Ollama;
    }

    /**
     * The fallback endpoint path for Ollama's native API.
     */
    public function ollamaFallbackPath(): string
    {
        return '/api/tags';
    }
}
