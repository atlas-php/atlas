<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Simple deterministic tool used by reasoning audits to force a multi-step
 * tool loop (so thinking-signature replay is exercised end to end).
 */
class MultiplyTool extends Tool
{
    public function name(): string
    {
        return 'multiply';
    }

    public function description(): string
    {
        return 'Multiply two integers and return the product.';
    }

    /**
     * @return array<int, mixed>
     */
    public function parameters(): array
    {
        return [
            Schema::integer('a', 'First integer'),
            Schema::integer('b', 'Second integer'),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): mixed
    {
        // Object-shaped so the result is portable across providers (Google's
        // functionResponse.response must be a struct, not a bare scalar).
        return json_encode(['product' => (int) $args['a'] * (int) $args['b']]);
    }
}
