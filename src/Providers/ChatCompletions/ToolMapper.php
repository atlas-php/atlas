<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ChatCompletions;

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Contracts\ToolMapperContract;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Support\Facades\Log;

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
     * @param  array<int, ToolDefinition>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function mapTools(array $tools): array
    {
        return array_map(fn (ToolDefinition $tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool->name,
                'description' => $tool->description,
                'parameters' => $tool->hasParameters() ? $tool->parameters : (object) [],
            ],
        ], $tools);
    }

    /**
     * Provider tools are not supported on Chat Completions.
     *
     * @param  array<int, ProviderTool>  $providerTools
     * @return array<int, array<string, mixed>>
     */
    public function mapProviderTools(array $providerTools): array
    {
        if ($providerTools !== []) {
            Log::warning('Provider tools are not supported on Chat Completions and will be ignored.', [
                'provider' => 'chat_completions',
                'tools' => array_map(fn (ProviderTool $t) => $t->type(), $providerTools),
            ]);
        }

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
            arguments: json_decode($tc['function']['arguments'] ?? '{}', true, 512, JSON_THROW_ON_ERROR),
        ), $rawToolCalls);
    }
}
