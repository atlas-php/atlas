<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Atlasphp\Atlas\Tools\ToolDefinition;

/**
 * Calculator tool for basic math operations.
 *
 * Demonstrates tool parameter handling with enums and numbers.
 */
class CalculatorTool extends ToolDefinition
{
    /**
     * Get the tool name.
     */
    public function name(): string
    {
        return 'calculator';
    }

    /**
     * Get the tool description.
     */
    public function description(): string
    {
        return 'Perform basic math operations: add, subtract, multiply, or divide two numbers.';
    }

    /**
     * Get the tool parameters.
     *
     * @return array<int, \Prism\Prism\Contracts\Schema>
     */
    public function parameters(): array
    {
        return [
            ToolParameter::enum(
                'operation',
                'The math operation to perform',
                ['add', 'subtract', 'multiply', 'divide'],
            ),
            ToolParameter::number(
                'a',
                'The first number',
            ),
            ToolParameter::number(
                'b',
                'The second number',
            ),
        ];
    }

    /**
     * Execute the tool.
     *
     * @param  array{operation: string, a: float|int, b: float|int}  $args
     */
    public function handle(array $args, ToolContext $context): ToolResult
    {
        $operation = $args['operation'] ?? '';
        $a = (float) ($args['a'] ?? 0);
        $b = (float) ($args['b'] ?? 0);

        $result = match ($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b !== 0.0 ? $a / $b : null,
            default => null,
        };

        if ($result === null) {
            if ($operation === 'divide' && $b === 0.0) {
                return ToolResult::error('Division by zero is not allowed.');
            }

            return ToolResult::error("Unknown operation: {$operation}");
        }

        // Format result to avoid floating point display issues
        $formatted = fmod($result, 1) === 0.0 ? (string) (int) $result : (string) round($result, 10);

        return ToolResult::text($formatted);
    }
}
