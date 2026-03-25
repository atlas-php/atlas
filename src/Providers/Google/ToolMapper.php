<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google;

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Contracts\ToolMapperContract;
use Atlasphp\Atlas\Tools\ToolDefinition;

/**
 * Maps Atlas tools to Gemini's function_declarations format and parses functionCall parts.
 */
class ToolMapper implements ToolMapperContract
{
    /**
     * Map Atlas ToolDefinitions to Gemini function declarations.
     *
     * @param  array<int, mixed>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function mapTools(array $tools): array
    {
        return array_map(fn (ToolDefinition $tool): array => [
            'name' => $tool->name,
            'description' => $tool->description,
            'parameters' => $tool->parameters !== [] ? $tool->parameters : ['type' => 'object', 'properties' => (object) []],
        ], $tools);
    }

    /**
     * Map provider tools to their native format.
     *
     * @param  array<int, mixed>  $providerTools
     * @return array<int, array<string, mixed>>
     */
    public function mapProviderTools(array $providerTools): array
    {
        return array_map(fn ($tool): array => $tool->toArray(), $providerTools);
    }

    /**
     * Parse functionCall parts from Gemini response into ToolCall objects.
     *
     * @param  array<int, array<string, mixed>>  $functionCallParts
     * @return array<int, ToolCall>
     */
    public function parseToolCalls(array $functionCallParts): array
    {
        return array_map(function (array $part, int $index): ToolCall {
            $fc = $part['functionCall'];

            return new ToolCall(
                id: $fc['id'] ?? 'gemini_call_'.$index,
                name: $fc['name'],
                arguments: $fc['args'] ?? [],
            );
        }, $functionCallParts, array_keys($functionCallParts));
    }
}
