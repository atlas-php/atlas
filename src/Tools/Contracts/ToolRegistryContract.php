<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Contracts;

/**
 * Contract for tool registry implementations.
 *
 * Defines the interface for registering and retrieving tool definitions.
 */
interface ToolRegistryContract
{
    /**
     * Register a tool class.
     *
     * @param  class-string<ToolContract>  $toolClass  The tool class to register.
     * @param  bool  $override  Whether to override if already registered.
     */
    public function register(string $toolClass, bool $override = false): void;

    /**
     * Register a tool instance directly.
     *
     * @param  ToolContract  $tool  The tool instance to register.
     * @param  bool  $override  Whether to override if already registered.
     */
    public function registerInstance(ToolContract $tool, bool $override = false): void;

    /**
     * Get a tool by its name.
     *
     * @param  string  $name  The tool name.
     */
    public function get(string $name): ToolContract;

    /**
     * Check if a tool is registered.
     *
     * @param  string  $name  The tool name.
     */
    public function has(string $name): bool;

    /**
     * Get all registered tools.
     *
     * @return array<string, ToolContract>
     */
    public function all(): array;

    /**
     * Get only the specified tools.
     *
     * @param  array<int, string>  $names  The tool names to retrieve.
     * @return array<string, ToolContract>
     */
    public function only(array $names): array;

    /**
     * Get all registered tool names.
     *
     * @return array<int, string>
     */
    public function names(): array;

    /**
     * Unregister a tool by its name.
     *
     * @param  string  $name  The tool name.
     * @return bool True if the tool was unregistered, false if not found.
     */
    public function unregister(string $name): bool;

    /**
     * Get the count of registered tools.
     */
    public function count(): int;

    /**
     * Clear all registered tools.
     */
    public function clear(): void;
}
