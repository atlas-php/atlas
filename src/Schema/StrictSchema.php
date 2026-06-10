<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema;

/**
 * Normalizes a JSON Schema array into the strict form required by OpenAI's
 * structured-output mode (and OpenAI-compatible providers such as xAI,
 * Ollama, and LM Studio).
 *
 * OpenAI strict `json_schema` requires, on every object node:
 *   - `additionalProperties: false`
 *   - every property listed in `required`
 *
 * Optional fields (present in `properties` but absent from `required`) may not
 * simply be omitted from `required` under strict mode; the documented pattern
 * is to keep them required and make their type nullable. This transformer
 * applies both rules recursively while preserving the builder's `optional()`
 * semantics.
 *
 * Pure and idempotent: re-normalizing an already-strict schema is a no-op,
 * so hand-written schemas that already satisfy strict mode pass through
 * unchanged.
 */
final class StrictSchema
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function normalize(array $schema): array
    {
        return self::normalizeNode($schema);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function normalizeNode(array $node): array
    {
        if (self::isObjectNode($node)) {
            $node = self::normalizeObject($node);
        }

        if (isset($node['items']) && is_array($node['items'])) {
            $node['items'] = self::normalizeNode($node['items']);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function normalizeObject(array $node): array
    {
        $properties = is_array($node['properties'] ?? null) ? $node['properties'] : [];
        $originalRequired = is_array($node['required'] ?? null) ? $node['required'] : [];

        foreach ($properties as $key => $propSchema) {
            if (! is_array($propSchema)) {
                continue;
            }

            $propSchema = self::normalizeNode($propSchema);

            if (! in_array($key, $originalRequired, true)) {
                $propSchema = self::makeNullable($propSchema);
            }

            $properties[$key] = $propSchema;
        }

        $node['properties'] = $properties;
        $node['required'] = array_keys($properties);
        $node['additionalProperties'] = false;

        return $node;
    }

    /**
     * Express an optional field as a nullable type union, the strict-mode
     * equivalent of omitting it from `required`.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function makeNullable(array $schema): array
    {
        $type = $schema['type'] ?? null;

        if (is_string($type) && $type !== 'null') {
            $schema['type'] = [$type, 'null'];
        } elseif (is_array($type) && ! in_array('null', $type, true)) {
            $type[] = 'null';
            $schema['type'] = $type;
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function isObjectNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'object' || isset($node['properties']);
    }
}
