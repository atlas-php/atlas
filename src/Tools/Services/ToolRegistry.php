<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Services;

use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use Atlasphp\Atlas\Tools\Exceptions\ToolException;
use Atlasphp\Atlas\Tools\Exceptions\ToolNotFoundException;
use Illuminate\Contracts\Container\Container;

/**
 * Registry for managing tool definitions.
 *
 * Provides registration, retrieval, and querying of tools by name.
 * Supports both class registration and instance registration.
 */
class ToolRegistry implements ToolRegistryContract
{
    /**
     * Registered tools keyed by their name.
     *
     * @var array<string, ToolContract>
     */
    protected array $tools = [];

    public function __construct(
        protected Container $container,
    ) {}

    /**
     * Register a tool class.
     *
     * @param  class-string<ToolContract>  $toolClass
     *
     * @throws ToolException If already registered and override is false.
     */
    public function register(string $toolClass, bool $override = false): void
    {
        /** @var ToolContract $tool */
        $tool = $this->container->make($toolClass);

        $this->registerInstance($tool, $override);
    }

    /**
     * Register a tool instance directly.
     *
     * @throws ToolException If already registered and override is false.
     */
    public function registerInstance(ToolContract $tool, bool $override = false): void
    {
        $name = $tool->name();

        if (! $override && isset($this->tools[$name])) {
            throw ToolException::duplicateRegistration($name);
        }

        $this->tools[$name] = $tool;
    }

    /**
     * Get a tool by its name.
     *
     * @throws ToolNotFoundException If the tool is not found.
     */
    public function get(string $name): ToolContract
    {
        if (! isset($this->tools[$name])) {
            throw ToolNotFoundException::forName($name);
        }

        return $this->tools[$name];
    }

    /**
     * Check if a tool is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Get all registered tools.
     *
     * @return array<string, ToolContract>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Get only the specified tools.
     *
     * @param  array<int, string>  $names
     * @return array<string, ToolContract>
     */
    public function only(array $names): array
    {
        return array_filter(
            $this->tools,
            fn (ToolContract $tool): bool => in_array($tool->name(), $names, true),
        );
    }

    /**
     * Get all registered tool names.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * Unregister a tool by its name.
     *
     * @return bool True if the tool was unregistered, false if not found.
     */
    public function unregister(string $name): bool
    {
        if (! isset($this->tools[$name])) {
            return false;
        }

        unset($this->tools[$name]);

        return true;
    }

    /**
     * Get the count of registered tools.
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Clear all registered tools.
     */
    public function clear(): void
    {
        $this->tools = [];
    }
}
