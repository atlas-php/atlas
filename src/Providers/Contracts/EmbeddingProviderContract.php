<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

/**
 * Contract for embedding providers.
 *
 * Defines the interface for generating text embeddings using various AI providers.
 */
interface EmbeddingProviderContract
{
    /**
     * Generate an embedding for a single text input.
     *
     * @param  string  $text  The text to embed.
     * @param  array<string, mixed>  $options  Additional options (dimensions, encoding_format, etc.).
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return array<int, float> The embedding vector.
     */
    public function generate(string $text, array $options = [], ?array $retry = null): array;

    /**
     * Generate embeddings for multiple text inputs.
     *
     * @param  array<int, string>  $texts  The texts to embed.
     * @param  array<string, mixed>  $options  Additional options (dimensions, encoding_format, etc.).
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return array<int, array<int, float>> Array of embedding vectors.
     */
    public function generateBatch(array $texts, array $options = [], ?array $retry = null): array;

    /**
     * Get the dimensions of the embedding vectors.
     *
     * @return int The number of dimensions.
     */
    public function dimensions(): int;

    /**
     * Get the provider name.
     *
     * @return string The provider name (e.g., 'openai', 'anthropic').
     */
    public function provider(): string;

    /**
     * Get the model name.
     *
     * @return string The model name (e.g., 'text-embedding-3-small').
     */
    public function model(): string;
}
