<?php

declare(strict_types=1);

use Atlasphp\Atlas\Models\Support\ModelResponseParser;

test('parseOpenAiCompatible extracts ids from data array', function (): void {
    $json = [
        'data' => [
            ['id' => 'gpt-4o', 'object' => 'model', 'created' => 1715367049],
            ['id' => 'gpt-3.5-turbo', 'object' => 'model', 'created' => 1677610602],
            ['id' => 'dall-e-3', 'object' => 'model', 'created' => 1698785189],
        ],
    ];

    $result = ModelResponseParser::parseOpenAiCompatible($json);

    expect($result)->toBe(['dall-e-3', 'gpt-3.5-turbo', 'gpt-4o']);
});

test('parseOpenAiCompatible returns empty array for missing data key', function (): void {
    expect(ModelResponseParser::parseOpenAiCompatible([]))->toBe([]);
});

test('parseAnthropic extracts ids from data array', function (): void {
    $json = [
        'data' => [
            ['id' => 'claude-sonnet-4-20250514', 'display_name' => 'Claude Sonnet 4', 'type' => 'model'],
            ['id' => 'claude-3-haiku-20240307', 'display_name' => 'Claude 3 Haiku', 'type' => 'model'],
        ],
    ];

    $result = ModelResponseParser::parseAnthropic($json);

    expect($result)->toBe(['claude-3-haiku-20240307', 'claude-sonnet-4-20250514']);
});

test('parseAnthropic handles missing display_name', function (): void {
    $json = [
        'data' => [
            ['id' => 'claude-test', 'type' => 'model'],
        ],
    ];

    $result = ModelResponseParser::parseAnthropic($json);

    expect($result)->toBe(['claude-test']);
});

test('parseGemini strips models prefix', function (): void {
    $json = [
        'models' => [
            ['name' => 'models/gemini-pro', 'displayName' => 'Gemini Pro', 'description' => 'Best model'],
            ['name' => 'models/gemini-1.5-flash', 'displayName' => 'Gemini 1.5 Flash'],
        ],
    ];

    $result = ModelResponseParser::parseGemini($json);

    expect($result)->toBe(['gemini-1.5-flash', 'gemini-pro']);
});

test('parseGemini handles name without models prefix', function (): void {
    $json = [
        'models' => [
            ['name' => 'gemini-pro', 'displayName' => 'Gemini Pro'],
        ],
    ];

    $result = ModelResponseParser::parseGemini($json);

    expect($result)->toBe(['gemini-pro']);
});

test('parseGemini handles missing displayName', function (): void {
    $json = [
        'models' => [
            ['name' => 'models/gemini-test'],
        ],
    ];

    $result = ModelResponseParser::parseGemini($json);

    expect($result)->toBe(['gemini-test']);
});

test('parseModelsArray extracts names from Ollama format', function (): void {
    $json = [
        'models' => [
            ['name' => 'llama2:latest', 'modified_at' => '2024-01-01T00:00:00Z'],
            ['name' => 'codellama:7b', 'modified_at' => '2024-01-01T00:00:00Z'],
            ['name' => 'mistral:latest', 'modified_at' => '2024-01-01T00:00:00Z'],
        ],
    ];

    $result = ModelResponseParser::parseModelsArray($json);

    expect($result)->toBe(['codellama:7b', 'llama2:latest', 'mistral:latest']);
});

test('parseModelsArray returns empty array for missing models key', function (): void {
    expect(ModelResponseParser::parseModelsArray([]))->toBe([]);
});

test('parseElevenLabs extracts model_id from flat array', function (): void {
    $json = [
        ['model_id' => 'eleven_v3', 'name' => 'Eleven v3', 'can_do_text_to_speech' => true],
        ['model_id' => 'eleven_flash_v2_5', 'name' => 'Eleven Flash v2.5', 'can_do_text_to_speech' => true],
        ['model_id' => 'eleven_multilingual_v2', 'name' => 'Eleven Multilingual v2'],
    ];

    $result = ModelResponseParser::parseElevenLabs($json);

    expect($result)->toBe(['eleven_flash_v2_5', 'eleven_multilingual_v2', 'eleven_v3']);
});

test('parseElevenLabs returns empty array for empty input', function (): void {
    expect(ModelResponseParser::parseElevenLabs([]))->toBe([]);
});

test('all parsers return results sorted alphabetically', function (): void {
    $openAi = ModelResponseParser::parseOpenAiCompatible([
        'data' => [
            ['id' => 'z-model'],
            ['id' => 'a-model'],
            ['id' => 'm-model'],
        ],
    ]);

    expect($openAi)->toBe(['a-model', 'm-model', 'z-model']);
});
