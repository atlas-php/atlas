<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Support;

use Atlasphp\Atlas\AtlasConfig;

/**
 * Global variable store for template interpolation across all modalities.
 *
 * Registered as a singleton. Supports static scalars, closures (resolved fresh
 * each call), nested arrays, and dotted keys. Merges three layers at resolution
 * time: config (lowest) → global registry → per-call runtime (highest).
 */
class VariableRegistry
{
    /** @var array<string, mixed> */
    protected array $variables = [];

    public function __construct(
        protected readonly AtlasConfig $config,
    ) {}

    /**
     * Register a variable.
     *
     * Accepts static scalars, closures (0-param or 1-param with meta),
     * nested arrays, or dotted keys.
     */
    public function register(string $name, mixed $value): void
    {
        $this->variables[$name] = $value;
    }

    /**
     * Register multiple variables at once.
     *
     * @param  array<string, mixed>  $variables
     */
    public function registerMany(array $variables): void
    {
        foreach ($variables as $name => $value) {
            $this->register($name, $value);
        }
    }

    /**
     * Unregister a variable.
     */
    public function unregister(string $name): void
    {
        unset($this->variables[$name]);
    }

    /**
     * Resolve all registered variables, invoking closures with the provided meta.
     *
     * @param  array<string, mixed>  $meta  Context passed to closure resolvers
     * @return array<string, mixed>
     */
    public function resolve(array $meta = []): array
    {
        return $this->resolveArray($this->variables, $meta);
    }

    /**
     * Merge config → registry → runtime into a single resolved array.
     *
     * @param  array<string, mixed>  $runtimeVariables  Per-call withVariables() values
     * @param  array<string, mixed>  $meta  Context for closure resolution
     * @return array<string, mixed>
     */
    public function merge(array $runtimeVariables = [], array $meta = []): array
    {
        $global = $this->resolve($meta);

        return array_replace_recursive($this->config->variables, $global, $runtimeVariables);
    }

    /**
     * Recursively resolve closures in an array.
     *
     * @param  array<string, mixed>  $items
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function resolveArray(array $items, array $meta): array
    {
        $resolved = [];

        foreach ($items as $key => $value) {
            if ($value instanceof \Closure) {
                $resolved[$key] = $this->invokeClosure($value, $meta);
            } elseif (is_array($value)) {
                $resolved[$key] = $this->resolveArray($value, $meta);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Invoke a closure, passing meta if it accepts a parameter.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function invokeClosure(\Closure $closure, array $meta): mixed
    {
        $reflection = new \ReflectionFunction($closure);

        if ($reflection->getNumberOfParameters() === 0) {
            return $closure();
        }

        return $closure($meta);
    }
}
