<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\Tools\Contracts\ConfiguresPrismTool;
use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Prism\Prism\Tool as PrismTool;

/**
 * Test fixture for a tool that implements ConfiguresPrismTool.
 *
 * Demonstrates custom Prism Tool configuration.
 */
class ConfigurableToolContract implements ConfiguresPrismTool, ToolContract
{
    public function name(): string
    {
        return 'configurable_tool';
    }

    public function description(): string
    {
        return 'A tool that configures the Prism Tool';
    }

    public function parameters(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function handle(array $args, ToolContext $context): ToolResult
    {
        return ToolResult::text('Handled');
    }

    public function configurePrismTool(PrismTool $tool): PrismTool
    {
        // Example: configure custom error handling
        return $tool->withProviderOptions(['custom_option' => true]);
    }
}
