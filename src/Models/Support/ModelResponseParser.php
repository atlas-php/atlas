<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Models\Support;

/**
 * Parses provider-specific JSON responses into a standardized model list format.
 *
 * Pure static utility with no side effects. Each method handles a specific
 * JSON structure and returns a sorted list of model entries.
 */
class ModelResponseParser
{
    /**
     * Parse OpenAI-compatible response format.
     *
     * Handles: OpenAI, Groq, DeepSeek, Mistral, XAI, OpenRouter.
     * Structure: { "data": [{ "id": "model-id" }] }
     *
     * @param  array<string, mixed>  $json
     * @return list<array{id: string, name: string|null}>
     */
    public static function parseOpenAiCompatible(array $json): array
    {
        $data = $json['data'] ?? [];

        $models = array_map(
            fn (array $model): array => [
                'id' => $model['id'],
                'name' => null,
            ],
            $data,
        );

        usort($models, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return $models;
    }

    /**
     * Parse Anthropic response format.
     *
     * Structure: { "data": [{ "id": "model-id", "display_name": "Model Name" }] }
     *
     * @param  array<string, mixed>  $json
     * @return list<array{id: string, name: string|null}>
     */
    public static function parseAnthropic(array $json): array
    {
        $data = $json['data'] ?? [];

        $models = array_map(
            fn (array $model): array => [
                'id' => $model['id'],
                'name' => $model['display_name'] ?? null,
            ],
            $data,
        );

        usort($models, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return $models;
    }

    /**
     * Parse Gemini response format.
     *
     * Structure: { "models": [{ "name": "models/gemini-pro", "displayName": "Gemini Pro" }] }
     * The "models/" prefix is stripped from the name field.
     *
     * @param  array<string, mixed>  $json
     * @return list<array{id: string, name: string|null}>
     */
    public static function parseGemini(array $json): array
    {
        $data = $json['models'] ?? [];

        $models = array_map(
            fn (array $model): array => [
                'id' => str_starts_with($model['name'], 'models/')
                    ? substr($model['name'], 7)
                    : $model['name'],
                'name' => $model['displayName'] ?? null,
            ],
            $data,
        );

        usort($models, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return $models;
    }

    /**
     * Parse Ollama native response format (from /api/tags).
     *
     * Structure: { "models": [{ "name": "llama2:latest" }] }
     *
     * @param  array<string, mixed>  $json
     * @return list<array{id: string, name: string|null}>
     */
    public static function parseModelsArray(array $json): array
    {
        $data = $json['models'] ?? [];

        $models = array_map(
            fn (array $model): array => [
                'id' => $model['name'],
                'name' => null,
            ],
            $data,
        );

        usort($models, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return $models;
    }

    /**
     * Parse ElevenLabs response format.
     *
     * Structure: [{ "model_id": "eleven_v3", "name": "Eleven v3" }]
     * Response is a flat JSON array (not wrapped in a data/models key).
     *
     * @param  list<array<string, mixed>>  $json
     * @return list<array{id: string, name: string|null}>
     */
    public static function parseElevenLabs(array $json): array
    {
        $models = array_map(
            fn (array $model): array => [
                'id' => $model['model_id'],
                'name' => $model['name'] ?? null,
            ],
            $json,
        );

        usort($models, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return $models;
    }
}
