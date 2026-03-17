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

    expect($result)->toBe([
        ['id' => 'dall-e-3', 'name' => null],
        ['id' => 'gpt-3.5-turbo', 'name' => null],
        ['id' => 'gpt-4o', 'name' => null],
    ]);
});

test('parseOpenAiCompatible returns empty array for missing data key', function (): void {
    expect(ModelResponseParser::parseOpenAiCompatible([]))->toBe([]);
});

test('parseAnthropic extracts ids and display names', function (): void {
    $json = [
        'data' => [
            ['id' => 'claude-sonnet-4-20250514', 'display_name' => 'Claude Sonnet 4', 'type' => 'model'],
            ['id' => 'claude-3-haiku-20240307', 'display_name' => 'Claude 3 Haiku', 'type' => 'model'],
        ],
    ];

    $result = ModelResponseParser::parseAnthropic($json);

    expect($result)->toBe([
        ['id' => 'claude-3-haiku-20240307', 'name' => 'Claude 3 Haiku'],
        ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4'],
    ]);
});

test('parseAnthropic handles missing display_name', function (): void {
    $json = [
        'data' => [
            ['id' => 'claude-test', 'type' => 'model'],
        ],
    ];

    $result = ModelResponseParser::parseAnthropic($json);

    expect($result)->toBe([
        ['id' => 'claude-test', 'name' => null],
    ]);
});

test('parseGemini strips models prefix and uses displayName', function (): void {
    $json = [
        'models' => [
            ['name' => 'models/gemini-pro', 'displayName' => 'Gemini Pro', 'description' => 'Best model'],
            ['name' => 'models/gemini-1.5-flash', 'displayName' => 'Gemini 1.5 Flash'],
        ],
    ];

    $result = ModelResponseParser::parseGemini($json);

    expect($result)->toBe([
        ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash'],
        ['id' => 'gemini-pro', 'name' => 'Gemini Pro'],
    ]);
});

test('parseGemini handles name without models prefix', function (): void {
    $json = [
        'models' => [
            ['name' => 'gemini-pro', 'displayName' => 'Gemini Pro'],
        ],
    ];

    $result = ModelResponseParser::parseGemini($json);

    expect($result)->toBe([
        ['id' => 'gemini-pro', 'name' => 'Gemini Pro'],
    ]);
});

test('parseGemini handles missing displayName', function (): void {
    $json = [
        'models' => [
            ['name' => 'models/gemini-test'],
        ],
    ];

    $result = ModelResponseParser::parseGemini($json);

    expect($result)->toBe([
        ['id' => 'gemini-test', 'name' => null],
    ]);
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

    expect($result)->toBe([
        ['id' => 'codellama:7b', 'name' => null],
        ['id' => 'llama2:latest', 'name' => null],
        ['id' => 'mistral:latest', 'name' => null],
    ]);
});

test('parseModelsArray returns empty array for missing models key', function (): void {
    expect(ModelResponseParser::parseModelsArray([]))->toBe([]);
});

test('parseElevenLabs extracts model_id and name from flat array', function (): void {
    $json = [
        ['model_id' => 'eleven_v3', 'name' => 'Eleven v3', 'can_do_text_to_speech' => true],
        ['model_id' => 'eleven_flash_v2_5', 'name' => 'Eleven Flash v2.5', 'can_do_text_to_speech' => true],
        ['model_id' => 'eleven_multilingual_v2', 'name' => 'Eleven Multilingual v2'],
    ];

    $result = ModelResponseParser::parseElevenLabs($json);

    expect($result)->toBe([
        ['id' => 'eleven_flash_v2_5', 'name' => 'Eleven Flash v2.5'],
        ['id' => 'eleven_multilingual_v2', 'name' => 'Eleven Multilingual v2'],
        ['id' => 'eleven_v3', 'name' => 'Eleven v3'],
    ]);
});

test('parseElevenLabs returns empty array for empty input', function (): void {
    expect(ModelResponseParser::parseElevenLabs([]))->toBe([]);
});

test('all parsers return results sorted by id', function (): void {
    $openAi = ModelResponseParser::parseOpenAiCompatible([
        'data' => [
            ['id' => 'z-model'],
            ['id' => 'a-model'],
            ['id' => 'm-model'],
        ],
    ]);

    expect($openAi[0]['id'])->toBe('a-model')
        ->and($openAi[1]['id'])->toBe('m-model')
        ->and($openAi[2]['id'])->toBe('z-model');
});
