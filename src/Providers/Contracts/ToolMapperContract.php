<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Tools\ToolChoice;
use Atlasphp\Atlas\Tools\ToolDefinition;

/**
 * Maps Atlas tool definitions to a provider's function calling format.
 */
interface ToolMapperContract
{
    /**
     * @param  array<int, ToolDefinition>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function mapTools(array $tools): array;

    /**
     * Translate a normalized tool choice into the provider's request-body
     * fragment (e.g. `['tool_choice' => 'required']`, or for Google a
     * `['tool_config' => [...]]`). Returns an empty array when the provider has
     * no representation for the choice.
     *
     * @return array<string, mixed>
     */
    public function mapToolChoice(ToolChoice $choice): array;

    /**
     * @param  array<int, ProviderTool>  $providerTools
     * @return array<int, array<string, mixed>>
     */
    public function mapProviderTools(array $providerTools): array;

    /**
     * @param  array<int, array<string, mixed>>  $rawToolCalls
     * @return array<int, ToolCall>
     */
    public function parseToolCalls(array $rawToolCalls): array;
}
