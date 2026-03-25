<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
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
