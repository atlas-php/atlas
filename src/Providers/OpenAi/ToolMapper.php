<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi;

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Contracts\ToolMapper as ToolMapperContract;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Tools\ToolDefinition;

/**
 * Maps Atlas tool definitions to OpenAI Responses API format.
 *
 * Uses the flat function format (no nested "function" key) and extracts
 * tool calls from output items using `call_id`.
 */
class ToolMapper implements ToolMapperContract
{
    /**
     * Map Atlas ToolDefinitions to Responses API flat function format.
     *
     * @param  array<int, mixed>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function mapTools(array $tools): array
    {
        return array_map(fn (ToolDefinition $tool) => [
            'type' => 'function',
            'name' => $tool->name,
            'description' => $tool->description,
            'parameters' => $tool->parameters !== [] ? $tool->parameters : (object) [],
            'strict' => true,
        ], $tools);
    }

    /**
     * Map Atlas provider tools to their native Responses API format.
     *
     * @param  array<int, mixed>  $providerTools
     * @return array<int, array<string, mixed>>
     */
    public function mapProviderTools(array $providerTools): array
    {
        return array_map(fn (ProviderTool $tool) => $tool->toArray(), $providerTools);
    }

    /**
     * Parse function_call output items into Atlas ToolCall objects.
     *
     * @param  array<int, array<string, mixed>>  $rawToolCalls
     * @return array<int, ToolCall>
     */
    public function parseToolCalls(array $rawToolCalls): array
    {
        return array_map(fn (array $item) => new ToolCall(
            id: $item['call_id'] ?? '',
            name: $item['name'] ?? '',
            arguments: json_decode($item['arguments'] ?? '{}', true) ?? [],
        ), $rawToolCalls);
    }
}
