<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Fluent builder for creating fake EmbeddingsResponse objects in tests.
 */
class EmbeddingsResponseFake
{
    /** @var array<int, array<int, float>> */
    protected array $embeddings = [[0.1, 0.2, 0.3]];

    protected Usage $usage;

    public function __construct()
    {
        $this->usage = new Usage(5, 0);
    }

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<int, array<int, float>>  $embeddings
     */
    public function withEmbeddings(array $embeddings): static
    {
        $this->embeddings = $embeddings;

        return $this;
    }

    public function withUsage(Usage $usage): static
    {
        $this->usage = $usage;

        return $this;
    }

    public function toResponse(): EmbeddingsResponse
    {
        return new EmbeddingsResponse(
            embeddings: $this->embeddings,
            usage: $this->usage,
        );
    }
}
