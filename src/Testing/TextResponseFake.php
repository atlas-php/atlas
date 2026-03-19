<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Fluent builder for creating fake TextResponse objects in tests.
 */
class TextResponseFake
{
    protected string $text = '';

    protected Usage $usage;

    protected FinishReason $finishReason = FinishReason::Stop;

    /** @var array<int, ToolCall> */
    protected array $toolCalls = [];

    protected ?string $reasoning = null;

    /** @var array<string, mixed> */
    protected array $meta = [];

    public function __construct()
    {
        $this->usage = new Usage(10, 20);
    }

    public static function make(): self
    {
        return new self;
    }

    public function withText(string $text): static
    {
        $this->text = $text;

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

    /**
     * @param  array<int, ToolCall>  $toolCalls
     */
    public function withToolCalls(array $toolCalls): static
    {
        $this->toolCalls = $toolCalls;
        $this->finishReason = FinishReason::ToolCalls;

        return $this;
    }

    public function withReasoning(?string $reasoning): static
    {
        $this->reasoning = $reasoning;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function toResponse(): TextResponse
    {
        return new TextResponse(
            text: $this->text,
            usage: $this->usage,
            finishReason: $this->finishReason,
            toolCalls: $this->toolCalls,
            reasoning: $this->reasoning,
            meta: $this->meta,
        );
    }
}
