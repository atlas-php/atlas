<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Support;

/**
 * Stateless engine for interpolating {VARIABLE} placeholders in template strings.
 *
 * Supports flat keys ({NAME}), dot notation ({COMPANY.NAME}), and multi-level
 * traversal ({A.B.C}). Unknown placeholders are left as-is.
 */
class VariableInterpolator
{
    /**
     * Interpolate {PLACEHOLDERS} in a template string.
     *
     * @param  array<string, mixed>  $variables  Resolved variables (may be nested)
     */
    public static function interpolate(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_.]*)\}/',
            function (array $matches) use ($variables): string {
                $value = static::resolve($matches[1], $variables);

                if ($value === null) {
                    return $matches[0];
                }

                return (string) $value;
            },
            $template,
        ) ?? $template;
    }

    /**
     * Resolve a potentially dotted key from a variables array.
     *
     * Resolution order:
     *   1. Exact flat key match (highest priority)
     *   2. Dot notation traversal
     *   3. null if not found
     */
    /**
     * @param  array<string, mixed>  $variables
     */
    public static function resolve(string $key, array $variables): mixed
    {
        // 1. Exact flat key match
        if (array_key_exists($key, $variables)) {
            $value = $variables[$key];

            if (is_array($value)) {
                return null;
            }

            return $value;
        }

        // 2. Dot notation traversal
        if (str_contains($key, '.')) {
            $segments = explode('.', $key);
            $current = $variables;

            foreach ($segments as $segment) {
                if (! is_array($current) || ! array_key_exists($segment, $current)) {
                    return null;
                }

                $current = $current[$segment];
            }

            if (is_array($current)) {
                return null;
            }

            return $current;
        }

        return null;
    }

    /**
     * Check if a template contains any variable placeholders.
     */
    public static function hasPlaceholders(string $template): bool
    {
        return (bool) preg_match('/\{[A-Za-z_][A-Za-z0-9_.]*\}/', $template);
    }
}
