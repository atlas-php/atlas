<?php

declare(strict_types=1);

use Atlasphp\Atlas\Models\Enums\ResponseFormat;

test('has expected cases', function (): void {
    $cases = ResponseFormat::cases();

    expect($cases)->toHaveCount(5);
});

test('case values are correct', function (): void {
    expect(ResponseFormat::OpenAiCompatible->value)->toBe('openai_compatible')
        ->and(ResponseFormat::Anthropic->value)->toBe('anthropic')
        ->and(ResponseFormat::Gemini->value)->toBe('gemini')
        ->and(ResponseFormat::ModelsArray->value)->toBe('models_array')
        ->and(ResponseFormat::ElevenLabs->value)->toBe('elevenlabs');
});

test('can be created from value', function (): void {
    expect(ResponseFormat::from('openai_compatible'))->toBe(ResponseFormat::OpenAiCompatible)
        ->and(ResponseFormat::from('anthropic'))->toBe(ResponseFormat::Anthropic)
        ->and(ResponseFormat::from('gemini'))->toBe(ResponseFormat::Gemini)
        ->and(ResponseFormat::from('models_array'))->toBe(ResponseFormat::ModelsArray)
        ->and(ResponseFormat::from('elevenlabs'))->toBe(ResponseFormat::ElevenLabs);
});

test('tryFrom returns null for invalid value', function (): void {
    expect(ResponseFormat::tryFrom('invalid'))->toBeNull();
});
