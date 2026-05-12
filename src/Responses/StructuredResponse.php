<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Enums\FinishReason;
use JsonSerializable;

/**
 * Response from a structured output request.
 */
class StructuredResponse implements JsonSerializable
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

    /**
     * @return array{structured: array<string, mixed>, usage: array<string, int>, finish_reason: string, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'structured' => $this->structured,
            'usage' => $this->usage->toArray(),
            'finish_reason' => $this->finishReason->value,
            'meta' => $this->meta,
        ];
    }

    /**
     * @return array{structured: array<string, mixed>, usage: array<string, int>, finish_reason: string, meta: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
