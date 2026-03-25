<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ChatCompletions;

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Contracts\ToolMapperContract;
use Atlasphp\Atlas\Tools\ToolDefinition;

/**
 * Maps Atlas tool definitions to Chat Completions nested function format.
 *
 * Uses `{"type": "function", "function": {...}}` wrapping and parses
 * tool calls with `id` (not `call_id`).
 */
class ToolMapper implements ToolMapperContract
{
    /**
     * Map Atlas ToolDefinitions to Chat Completions nested function format.
     *
     * @param  array<int, mixed>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function mapTools(array $tools): array
    {
        return array_map(fn (ToolDefinition $tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool->name,
                'description' => $tool->description,
                'parameters' => $tool->parameters !== [] ? $tool->parameters : (object) [],
            ],
        ], $tools);
    }

    /**
     * Provider tools are not supported on Chat Completions.
     *
     * @param  array<int, mixed>  $providerTools
     * @return array<int, array<string, mixed>>
     */
    public function mapProviderTools(array $providerTools): array
    {
        return [];
    }

    /**
     * Parse Chat Completions tool_calls into Atlas ToolCall objects.
     *
     * @param  array<int, array<string, mixed>>  $rawToolCalls
     * @return array<int, ToolCall>
     */
    public function parseToolCalls(array $rawToolCalls): array
    {
        return array_map(fn (array $tc) => new ToolCall(
            id: $tc['id'] ?? '',
            name: $tc['function']['name'] ?? '',
            arguments: json_decode($tc['function']['arguments'] ?? '{}', true) ?? [],
        ), $rawToolCalls);
    }
}
