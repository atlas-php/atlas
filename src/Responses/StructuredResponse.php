<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Enums\FinishReason;

/**
 * Response from a structured output request.
 */
class StructuredResponse
{
    /**
     * @param  array<string, mixed>  $structured
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly array $structured,
        public readonly Usage $usage,
        public readonly FinishReason $finishReason,
        public readonly array $meta = [],
    ) {}
}
