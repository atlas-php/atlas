<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools;

use JsonSerializable;

/**
 * Converts tool handle() return values to strings the model can read.
 */
class ToolSerializer
{
    /**
     * Serialize a tool result to a string.
     */
    public static function serialize(mixed $result): string
    {
        return match (true) {
            is_string($result) => $result,

            is_array($result) => json_encode($result, JSON_THROW_ON_ERROR),

            $result instanceof JsonSerializable => json_encode($result, JSON_THROW_ON_ERROR),

            is_object($result) && method_exists($result, 'toArray') => json_encode($result->toArray(), JSON_THROW_ON_ERROR),

            is_object($result) && method_exists($result, 'toJson') => (string) $result->toJson(),

            is_bool($result) => $result ? 'true' : 'false',

            is_int($result) || is_float($result) => (string) $result,

            is_null($result) => 'No result returned.',

            is_object($result) => json_encode((array) $result, JSON_THROW_ON_ERROR),

            default => (string) $result,
        };
    }
}
