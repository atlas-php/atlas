<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Fluent builder for creating fake StructuredResponse objects in tests.
 */
class StructuredResponseFake
{
    /** @var array<string, mixed> */
    protected array $structured = [];

    protected Usage $usage;

    protected FinishReason $finishReason = FinishReason::Stop;

    public function __construct()
    {
        $this->usage = new Usage(10, 20);
    }

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    public function withStructured(array $structured): static
    {
        $this->structured = $structured;

        return $this;
    }

    public function withUsage(Usage $usage): static
    {
        $this->usage = $usage;

        return $this;
    }

    public function withFinishReason(FinishReason $finishReason): static
    {
        $this->finishReason = $finishReason;

        return $this;
    }

    public function toResponse(): StructuredResponse
    {
        return new StructuredResponse(
            structured: $this->structured,
            usage: $this->usage,
            finishReason: $this->finishReason,
        );
    }
}
