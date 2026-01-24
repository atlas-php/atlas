<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\Contracts\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Contracts\Tools\Support\ToolContext;
use Atlasphp\Atlas\Contracts\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Contracts\Tools\Support\ToolResult;

/**
 * Test fixture that implements ToolContract directly without extending ToolDefinition.
 * Used to test the buildPrismToolManually path in ToolBuilder.
 */
class RawToolContract implements ToolContract
{
    public function name(): string
    {
        return 'raw_tool';
    }

    public function description(): string
    {
        return 'A raw tool that implements ToolContract directly';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('query', 'The search query', required: true),
            ToolParameter::integer('limit', 'Maximum results', required: false),
        ];
    }

    public function handle(array $args, ToolContext $context): ToolResult
    {
        $query = $args['query'] ?? '';
        $limit = $args['limit'] ?? 10;

        return ToolResult::text("Searched for: {$query} (limit: {$limit})");
    }
}
