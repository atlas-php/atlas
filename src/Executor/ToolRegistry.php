<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Executor;

use Atlasphp\Atlas\Exceptions\ToolNotFoundException;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Indexes tools by name for fast lookup during execution.
 */
class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /**
     * @param  array<int, Tool>  $tools
     */
    public function __construct(array $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * Resolve a tool by its name.
     *
     * @throws ToolNotFoundException If the tool is not registered.
     */
    public function resolve(string $name): Tool
    {
        if (! isset($this->tools[$name])) {
            throw new ToolNotFoundException($name);
        }

        return $this->tools[$name];
    }

    /**
     * Determine if a tool is registered by name.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Get all registered tools indexed by name.
     *
     * @return array<string, Tool>
     */
    public function all(): array
    {
        return $this->tools;
    }
}
