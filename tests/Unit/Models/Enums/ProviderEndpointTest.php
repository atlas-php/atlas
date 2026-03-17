<?php

declare(strict_types=1);

use Atlasphp\Atlas\Models\Enums\ProviderEndpoint;
use Atlasphp\Atlas\Models\Enums\ResponseFormat;

test('has 13 cases matching Prism providers', function (): void {
    expect(ProviderEndpoint::cases())->toHaveCount(13);
});

test('hasModelsEndpoint returns false for unsupported providers', function (): void {
    expect(ProviderEndpoint::Perplexity->hasModelsEndpoint())->toBeFalse()
        ->and(ProviderEndpoint::VoyageAI->hasModelsEndpoint())->toBeFalse()
        ->and(ProviderEndpoint::Z->hasModelsEndpoint())->toBeFalse();
});

test('hasModelsEndpoint returns true for supported providers', function (ProviderEndpoint $endpoint): void {
    expect($endpoint->hasModelsEndpoint())->toBeTrue();
})->with([
    ProviderEndpoint::OpenAI,
    ProviderEndpoint::Anthropic,
    ProviderEndpoint::Gemini,
    ProviderEndpoint::Ollama,
    ProviderEndpoint::DeepSeek,
    ProviderEndpoint::Mistral,
    ProviderEndpoint::Groq,
    ProviderEndpoint::XAI,
    ProviderEndpoint::OpenRouter,
    ProviderEndpoint::ElevenLabs,
]);

test('endpointPath returns correct paths', function (): void {
    expect(ProviderEndpoint::OpenAI->endpointPath())->toBe('/models')
        ->and(ProviderEndpoint::Anthropic->endpointPath())->toBe('/models')
        ->and(ProviderEndpoint::Gemini->endpointPath())->toBeNull()
        ->and(ProviderEndpoint::Ollama->endpointPath())->toBe('/v1/models')
        ->and(ProviderEndpoint::DeepSeek->endpointPath())->toBe('/models')
        ->and(ProviderEndpoint::Mistral->endpointPath())->toBe('/models')
        ->and(ProviderEndpoint::Groq->endpointPath())->toBe('/models')
        ->and(ProviderEndpoint::XAI->endpointPath())->toBe('/models')
        ->and(ProviderEndpoint::OpenRouter->endpointPath())->toBe('/models')
        ->and(ProviderEndpoint::ElevenLabs->endpointPath())->toBe('/models')
        ->and(ProviderEndpoint::Perplexity->endpointPath())->toBeNull()
        ->and(ProviderEndpoint::VoyageAI->endpointPath())->toBeNull()
        ->and(ProviderEndpoint::Z->endpointPath())->toBeNull();
});

test('responseFormat returns correct format for each provider', function (): void {
    expect(ProviderEndpoint::OpenAI->responseFormat())->toBe(ResponseFormat::OpenAiCompatible)
        ->and(ProviderEndpoint::Anthropic->responseFormat())->toBe(ResponseFormat::Anthropic)
        ->and(ProviderEndpoint::Gemini->responseFormat())->toBe(ResponseFormat::Gemini)
        ->and(ProviderEndpoint::Ollama->responseFormat())->toBe(ResponseFormat::OpenAiCompatible)
        ->and(ProviderEndpoint::DeepSeek->responseFormat())->toBe(ResponseFormat::OpenAiCompatible)
        ->and(ProviderEndpoint::ElevenLabs->responseFormat())->toBe(ResponseFormat::ElevenLabs)
        ->and(ProviderEndpoint::Perplexity->responseFormat())->toBeNull()
        ->and(ProviderEndpoint::VoyageAI->responseFormat())->toBeNull()
        ->and(ProviderEndpoint::Z->responseFormat())->toBeNull();
});

test('buildHeaders returns Bearer token for standard providers', function (): void {
    $headers = ProviderEndpoint::OpenAI->buildHeaders('test-key');

    expect($headers)->toBe(['Authorization' => 'Bearer test-key']);
});

test('buildHeaders returns x-api-key and version for Anthropic', function (): void {
    $headers = ProviderEndpoint::Anthropic->buildHeaders('test-key');

    expect($headers)->toBe([
        'x-api-key' => 'test-key',
        'anthropic-version' => '2023-06-01',
    ]);
});

test('buildHeaders returns xi-api-key for ElevenLabs', function (): void {
    $headers = ProviderEndpoint::ElevenLabs->buildHeaders('test-key');

    expect($headers)->toBe(['xi-api-key' => 'test-key']);
});

test('buildHeaders returns empty array for Gemini', function (): void {
    $headers = ProviderEndpoint::Gemini->buildHeaders('test-key');

    expect($headers)->toBe([]);
});

test('buildUrl appends endpoint path for standard providers', function (): void {
    $url = ProviderEndpoint::OpenAI->buildUrl('https://api.openai.com/v1', 'key');

    expect($url)->toBe('https://api.openai.com/v1/models');
});

test('buildUrl appends query param for Gemini', function (): void {
    $url = ProviderEndpoint::Gemini->buildUrl('https://generativelanguage.googleapis.com/v1beta/models', 'my-key');

    expect($url)->toBe('https://generativelanguage.googleapis.com/v1beta/models?key=my-key');
});

test('buildUrl handles trailing slash in base URL', function (): void {
    $url = ProviderEndpoint::OpenAI->buildUrl('https://api.openai.com/v1/', 'key');

    expect($url)->toBe('https://api.openai.com/v1/models');
});

test('buildUrl for Ollama', function (): void {
    $url = ProviderEndpoint::Ollama->buildUrl('http://localhost:11434', '');

    expect($url)->toBe('http://localhost:11434/v1/models');
});

test('isKeyless returns true only for Ollama', function (): void {
    expect(ProviderEndpoint::Ollama->isKeyless())->toBeTrue()
        ->and(ProviderEndpoint::OpenAI->isKeyless())->toBeFalse()
        ->and(ProviderEndpoint::Anthropic->isKeyless())->toBeFalse();
});

test('ollamaFallbackPath returns api tags path', function (): void {
    expect(ProviderEndpoint::Ollama->ollamaFallbackPath())->toBe('/api/tags');
});
