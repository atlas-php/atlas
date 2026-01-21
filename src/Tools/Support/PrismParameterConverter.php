<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Support;

use Prism\Prism\Tool as PrismTool;

/**
 * Converts ToolParameter to Prism Tool parameter format.
 *
 * Shared utility class for adding parameters to Prism tools,
 * used by both ToolDefinition and ToolBuilder.
 */
class PrismParameterConverter
{
    /**
     * Add a parameter to a Prism Tool.
     */
    public static function addParameter(PrismTool $tool, ToolParameter $param): void
    {
        $method = match ($param->type) {
            'string' => 'withStringParameter',
            'integer' => 'withNumberParameter',
            'number' => 'withNumberParameter',
            'boolean' => 'withBooleanParameter',
            'array' => 'withArrayParameter',
            'object' => 'withObjectParameter',
            default => 'withStringParameter',
        };

        if ($param->enum !== null) {
            $tool->withEnumParameter(
                $param->name,
                $param->description,
                $param->enum,
                $param->required,
            );

            return;
        }

        if ($method === 'withArrayParameter' || $method === 'withObjectParameter') {
            $schema = $param->toPrismSchema();
            $tool->withParameter($schema, $param->required);

            return;
        }

        $tool->{$method}($param->name, $param->description, $param->required);
    }
}
