<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\Contracts\Tools\Support\ToolContext;
use Atlasphp\Atlas\Contracts\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Contracts\Tools\Support\ToolResult;
use Atlasphp\Atlas\Contracts\Tools\ToolDefinition;

/**
 * Test tool fixture for unit and feature tests.
 */
class TestTool extends ToolDefinition
{
    public function name(): string
    {
        return 'test_tool';
    }

    public function description(): string
    {
        return 'A test tool that echoes input back.';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('input', 'The input to echo back'),
            ToolParameter::boolean('uppercase', 'Whether to uppercase the output', false, false),
        ];
    }

    public function handle(array $args, ToolContext $context): ToolResult
    {
        $input = $args['input'] ?? '';
        $uppercase = $args['uppercase'] ?? false;

        $output = $uppercase ? strtoupper($input) : $input;

        return ToolResult::text("Result: {$output}");
    }
}
