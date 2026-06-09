<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Anthropic;

use Atlasphp\Atlas\Enums\ToolChoiceMode;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Contracts\ToolMapperContract;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Tools\ToolChoice;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Support\Facades\Log;

/**
 * Maps Atlas tools to Anthropic's tool format and parses tool_use content blocks.
 */
class ToolMapper implements ToolMapperContract
{
    /**
     * Neutral provider-tool type → Anthropic's versioned server-tool identity.
     *
     * Anthropic names server tools with a dated `type` plus a stable `name`.
     * Tool-specific attributes (max_uses, allowed_domains, blocked_domains,
     * user_location, …) sit top-level on the tool object and pass through
     * untouched from the tool's options bag.
     *
     * @var array<string, array{type: string, name: string}>
     */
    private const SUPPORTED = [
        'web_search' => ['type' => 'web_search_20250305', 'name' => 'web_search'],
        'web_fetch' => ['type' => 'web_fetch_20250910', 'name' => 'web_fetch'],
    ];

    /**
     * Map Atlas ToolDefinitions to Anthropic tool format.
     *
     * @param  array<int, ToolDefinition>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function mapTools(array $tools): array
    {
        return array_map(fn (ToolDefinition $tool): array => [
            'name' => $tool->name,
            'description' => $tool->description,
            'input_schema' => $tool->hasParameters() ? $tool->parameters : ['type' => 'object', 'properties' => (object) []],
        ], $tools);
    }

    /**
     * Map a normalized tool choice to Anthropic's `tool_choice` object:
     * `{type:auto}`, `{type:any}` (require any tool), `{type:tool, name}`
     * (require a specific tool), or `{type:none}`.
     *
     * @return array<string, mixed>
     */
    public function mapToolChoice(ToolChoice $choice): array
    {
        return ['tool_choice' => match ($choice->mode) {
            ToolChoiceMode::Auto => ['type' => 'auto'],
            ToolChoiceMode::None => ['type' => 'none'],
            ToolChoiceMode::Required => $choice->tool !== null
                ? ['type' => 'tool', 'name' => $choice->tool]
                : ['type' => 'any'],
        }];
    }

    /**
     * Map provider tools to their native format.
     *
     * @param  array<int, ProviderTool>  $providerTools
     * @return array<int, array<string, mixed>>
     */
    public function mapProviderTools(array $providerTools): array
    {
        $mapped = [];
        $unsupported = [];

        foreach ($providerTools as $tool) {
            $native = self::SUPPORTED[$tool->type()] ?? null;

            if ($native === null) {
                $unsupported[] = $tool->type();

                continue;
            }

            // Start from the neutral payload (top-level attributes + options
            // bag), then swap in Anthropic's versioned type and stable name.
            $mapped[] = array_merge($tool->toArray(), [
                'type' => $native['type'],
                'name' => $native['name'],
            ]);
        }

        if ($unsupported !== []) {
            Log::warning('Some provider tools are not supported on Anthropic and were ignored.', [
                'provider' => 'anthropic',
                'tools' => $unsupported,
            ]);
        }

        return $mapped;
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
