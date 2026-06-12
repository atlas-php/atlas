<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

/**
 * Thrown for batch-assembly and batch-lifecycle errors.
 *
 * Batch lines are one-shot, fire-and-forget requests resolved hours later and
 * out of band. Features that depend on the synchronous request pipeline — the
 * tool-execution loop and the middleware stack — cannot apply to a batched
 * line, so attaching them is rejected up front rather than silently ignored.
 */
class BatchException extends AtlasException
{
    /**
     * The batch had no lines to submit.
     */
    public static function empty(): self
    {
        return new self('Cannot submit an empty batch — add at least one request first.');
    }

    /**
     * A request type that cannot be batched was added.
     */
    public static function notBatchable(string $class): self
    {
        return new self(
            "Requests of type [{$class}] cannot be batched. Only text and embeddings requests are batchable."
        );
    }

    /**
     * Two different modalities were mixed in one batch.
     */
    public static function mixedModality(string $expected, string $got): self
    {
        return new self(
            "All requests in a batch must share one modality; expected [{$expected}], got [{$got}]."
        );
    }

    /**
     * Two different providers were mixed in one batch.
     */
    public static function mixedProvider(string $expected, string $got): self
    {
        return new self(
            "All requests in a batch must target one provider; expected [{$expected}], got [{$got}]."
        );
    }

    /**
     * A request with tools was added to a batch.
     */
    public static function toolsUnsupported(): self
    {
        return new self(
            'Tools cannot be used with batch processing. The tool-execution loop requires '
            .'synchronous round-trips (model → tool → model), but a batch line is a single '
            .'one-shot request resolved later. Run tool-using requests synchronously, or via an agent.'
        );
    }

    /**
     * A request with per-request middleware was added to a batch.
     */
    public static function middlewareUnsupported(): self
    {
        return new self(
            'Per-request middleware does not run for batched requests. Batch bypasses the '
            .'synchronous middleware pipeline — the line is serialized to its provider payload and '
            .'submitted as a deferred job. Apply any transformation before adding the request, or '
            .'process results when the batch completes.'
        );
    }

    /**
     * Batch tracking was requested without persistence enabled.
     */
    public static function persistenceRequired(): self
    {
        return new self(
            'Batch tracking requires atlas persistence to be enabled. Enable '
            .'atlas.persistence.enabled, or use stateless submit() and poll the provider yourself.'
        );
    }
}
