<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Responses;

use Atlasphp\Atlas\Enums\ToolChoiceMode;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Contracts\ToolMapperContract;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Tools\ToolChoice;
use Atlasphp\Atlas\Tools\ToolDefinition;

/**
 * Maps Atlas tool definitions to the OpenAI Responses API format.
 *
 * Shared by every provider that speaks the Responses API wire format. Uses the
 * flat function format (no nested "function" key) and extracts tool calls from
 * output items using `call_id`. Providers override the protected hooks
 * (`resolveCallId`, `mapProviderTool`) only where their behavior diverges.
 */
class ToolMapper implements ToolMapperContract
{
    /**
     * Map Atlas ToolDefinitions to Responses API flat function format.
     *
     * @param  array<int, ToolDefinition>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function mapTools(array $tools): array
    {
        return array_map(function (ToolDefinition $tool) {
            $mapped = [
                'type' => 'function',
                'name' => $tool->name,
                'description' => $tool->description,
                'parameters' => $tool->hasParameters() ? $tool->parameters : (object) [],
            ];

            // Strict mode requires ALL properties in required — only enable
            // when every property is required (no optional parameters).
            if ($this->canBeStrict($tool->parameters)) {
                $mapped['strict'] = true;
            }

            return $mapped;
        }, $tools);
    }

    /**
     * Determine if tool parameters qualify for strict mode.
     * Strict requires all properties listed in required.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function canBeStrict(array $parameters): bool
    {
        if ($parameters === []) {
            return true;
        }

        // No required key means all properties are implicitly required (strict OK)
        if (! array_key_exists('required', $parameters)) {
            return true;
        }

        $properties = $parameters['properties'] ?? [];
        $required = $parameters['required'] ?? [];

        return count($properties) === count($required);
    }

    /**
     * Map a normalized tool choice to the Responses API `tool_choice` shape:
     * the strings `auto`/`required`/`none`, or a flat `{type:function, name}`
     * object to force a specific tool.
     *
     * @return array<string, mixed>
     */
    public function mapToolChoice(ToolChoice $choice): array
    {
        return ['tool_choice' => match ($choice->mode) {
            ToolChoiceMode::Auto => 'auto',
            ToolChoiceMode::None => 'none',
            ToolChoiceMode::Required => $choice->tool !== null
                ? ['type' => 'function', 'name' => $choice->tool]
                : 'required',
        }];
    }

    /**
     * Map Atlas provider tools to their native Responses API format.
     *
     * @param  array<int, ProviderTool>  $providerTools
     * @return array<int, array<string, mixed>>
     */
    public function mapProviderTools(array $providerTools): array
    {
        return array_map(fn (ProviderTool $tool): array => $this->mapProviderTool($tool), $providerTools);
    }

    /**
     * Translate one provider tool to its Responses API shape.
     *
     * The base shape is the tool's provider-neutral `toArray()`; per-type tweaks
     * adapt it to the Responses API request format without altering tools that
     * already work.
     *
     * @return array<string, mixed>
     */
    protected function mapProviderTool(ProviderTool $tool): array
    {
        $payload = $tool->toArray();

        // web_search nests domain restrictions under `filters`. Tools without
        // domain scoping are emitted byte-for-byte as before.
        if ($tool->type() === 'web_search') {
            return $this->nestWebSearchFilters($payload);
        }

        return $payload;
    }

    /**
     * Move neutral `allowed_domains` / `blocked_domains` into the Responses API
     * `filters` object (web_search). All other attributes — `search_context_size`,
     * `user_location`, and anything passed through the tool's options bag — are
     * left untouched so future options pass through.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function nestWebSearchFilters(array $payload): array
    {
        $filters = [];

        foreach (['allowed_domains', 'blocked_domains'] as $key) {
            if (isset($payload[$key])) {
                $filters[$key] = $payload[$key];
                unset($payload[$key]);
            }
        }

        if ($filters !== []) {
            $payload['filters'] = $filters;
        }

        return $payload;
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
            id: $this->resolveCallId($item),
            name: $item['name'] ?? '',
            arguments: json_decode($item['arguments'] ?? '{}', true, 512, JSON_THROW_ON_ERROR),
        ), $rawToolCalls);
    }

    /**
     * Resolve the tool call ID from an output item.
     *
     * The Responses API standard is `call_id`, falling back to `id` when it is
     * absent. Providers whose models return non-standard ids (e.g. xAI's
     * sequential indices) override this.
     *
     * @param  array<string, mixed>  $item
     */
    protected function resolveCallId(array $item): string
    {
        $callId = $item['call_id'] ?? '';

        return $callId !== '' ? $callId : ($item['id'] ?? '');
    }
}
