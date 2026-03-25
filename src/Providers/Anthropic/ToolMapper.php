<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Anthropic;

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Contracts\ToolMapperContract;
use Atlasphp\Atlas\Tools\ToolDefinition;

/**
 * Maps Atlas tools to Anthropic's tool format and parses tool_use content blocks.
 */
class ToolMapper implements ToolMapperContract
{
    /**
     * Map Atlas ToolDefinitions to Anthropic tool format.
     *
     * @param  array<int, mixed>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function mapTools(array $tools): array
    {
        return array_map(fn (ToolDefinition $tool): array => [
            'name' => $tool->name,
            'description' => $tool->description,
            'input_schema' => $tool->parameters !== [] ? $tool->parameters : ['type' => 'object', 'properties' => (object) []],
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
        return [];
    }

    /**
     * Parse tool_use content blocks from Anthropic response into ToolCall objects.
     *
     * @param  array<int, array<string, mixed>>  $toolUseBlocks
     * @return array<int, ToolCall>
     */
    public function parseToolCalls(array $toolUseBlocks): array
    {
        return array_map(fn (array $block): ToolCall => new ToolCall(
            id: $block['id'] ?? '',
            name: $block['name'] ?? '',
            arguments: $block['input'] ?? [],
        ), $toolUseBlocks);
    }
}
