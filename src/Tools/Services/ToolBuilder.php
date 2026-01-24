<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Tools\Contracts\ConfiguresPrismTool;
use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Contracts\Container\Container;
use Prism\Prism\Tool as PrismTool;

/**
 * Builds Prism Tool instances for agent execution.
 *
 * Resolves tool classes, wraps their handlers with the executor,
 * and converts them to Prism Tool format.
 */
class ToolBuilder
{
    public function __construct(
        protected ToolRegistryContract $registry,
        protected ToolExecutor $executor,
        protected Container $container,
    ) {}

    /**
     * Build Prism tools for an agent.
     *
     * @param  AgentContract  $agent  The agent to build tools for.
     * @param  ToolContext  $context  The tool execution context.
     * @return array<int, PrismTool>
     */
    public function buildForAgent(AgentContract $agent, ToolContext $context): array
    {
        $toolClasses = $agent->tools();

        if ($toolClasses === []) {
            return [];
        }

        $prismTools = [];

        foreach ($toolClasses as $toolClass) {
            $tool = $this->resolveTool($toolClass);
            $prismTools[] = $this->buildPrismTool($tool, $context);
        }

        return $prismTools;
    }

    /**
     * Build Prism tools from a list of tool class names.
     *
     * @param  array<int, class-string<ToolContract>>  $toolClasses
     * @param  ToolContext  $context  The tool execution context.
     * @return array<int, PrismTool>
     */
    public function buildFromClasses(array $toolClasses, ToolContext $context): array
    {
        $prismTools = [];

        foreach ($toolClasses as $toolClass) {
            $tool = $this->resolveTool($toolClass);
            $prismTools[] = $this->buildPrismTool($tool, $context);
        }

        return $prismTools;
    }

    /**
     * Build Prism tools from a list of tool instances.
     *
     * @param  array<int, ToolContract>  $tools
     * @param  ToolContext  $context  The tool execution context.
     * @return array<int, PrismTool>
     */
    public function buildFromInstances(array $tools, ToolContext $context): array
    {
        $prismTools = [];

        foreach ($tools as $tool) {
            $prismTools[] = $this->buildPrismTool($tool, $context);
        }

        return $prismTools;
    }

    /**
     * Resolve a tool from its class name.
     *
     * First checks the registry, then attempts container resolution.
     *
     * @param  class-string<ToolContract>  $toolClass
     */
    protected function resolveTool(string $toolClass): ToolContract
    {
        // Try to get an instance from the container
        /** @var ToolContract $tool */
        $tool = $this->container->make($toolClass);

        return $tool;
    }

    /**
     * Build a Prism Tool from an Atlas tool.
     */
    protected function buildPrismTool(ToolContract $tool, ToolContext $context): PrismTool
    {
        // Create the handler that uses the executor
        // Use variadic args since Prism unpacks tool arguments as named parameters
        $handler = fn (...$args): string => $this->executor
            ->execute($tool, $args, $context)
            ->text;

        // If it's a ToolDefinition, use the built-in converter
        if ($tool instanceof ToolDefinition) {
            return $tool->toPrismTool($handler);
        }

        // Otherwise, build manually
        return $this->buildPrismToolManually($tool, $handler);
    }

    /**
     * Build a Prism Tool manually for non-ToolDefinition tools.
     */
    protected function buildPrismToolManually(ToolContract $tool, callable $handler): PrismTool
    {
        $prismTool = new PrismTool;
        $prismTool->as($tool->name());
        $prismTool->for($tool->description());

        foreach ($tool->parameters() as $schema) {
            $prismTool->withParameter($schema);
        }

        $prismTool->using($handler);

        if ($tool instanceof ConfiguresPrismTool) {
            return $tool->configurePrismTool($prismTool);
        }

        return $prismTool;
    }
}
