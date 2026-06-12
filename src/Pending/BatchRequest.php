<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Batch\BatchService;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\BatchException;
use Atlasphp\Atlas\Pending\Contracts\Batchable;
use Atlasphp\Atlas\Persistence\Models\BatchGroup;
use Atlasphp\Atlas\Persistence\Models\BatchJob;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Requests\Batch;
use Atlasphp\Atlas\Requests\BatchLine;
use Atlasphp\Atlas\Responses\BatchResponse;

/**
 * Fluent collector for assembling and submitting a batch job.
 *
 * Each added request is an ordinary pending modality builder (text or
 * embeddings). A batch targets a single provider and a single modality; the
 * first added request fixes both, and mismatched additions are rejected.
 *
 * Persistence is ambient: when `atlas.persistence.enabled` is on, submit()
 * persists a {@see BatchJob} that the atlas:batch-poll command hydrates
 * automatically; when off, submit() is stateless and returns a
 * {@see BatchResponse} with the provider batch id for the caller to poll.
 */
class BatchRequest
{
    protected ?string $provider;

    protected ?Modality $modality = null;

    /** @var array<int, BatchLine> */
    protected array $lines = [];

    protected ?BatchGroup $group = null;

    protected string $completionWindow = '24h';

    public function __construct(
        Provider|string|null $provider,
        protected readonly ProviderRegistryContract $registry,
        protected readonly ?BatchService $batchService = null,
    ) {
        $this->provider = $provider === null ? null : Provider::normalize($provider);
        $this->completionWindow = (string) config('atlas.batch.completion_window', '24h');
    }

    /**
     * Add a request to the batch, keyed for trace-back on results.
     *
     * The key is echoed by the provider on the matching result; use your own
     * model's primary key so results map straight back to your records.
     *
     * @throws BatchException When the request is not batchable, mixes providers/
     *                        modalities, or carries tools or middleware (neither
     *                        survives a batch line).
     */
    public function add(object $request, string $key): static
    {
        if (! $request instanceof Batchable) {
            throw BatchException::notBatchable($request::class);
        }

        $this->bindModalityAndProvider($request);

        $dto = $request->buildRequest();
        $this->assertNoLoopFeatures($dto);

        $this->lines[] = new BatchLine($key, $dto);

        return $this;
    }

    /**
     * Add many requests via a factory returning [Batchable $request, string $key].
     *
     * @param  iterable<mixed>  $items
     * @param  callable(mixed): array{0: object, 1: string}  $factory
     */
    public function addMany(iterable $items, callable $factory): static
    {
        foreach ($items as $item) {
            [$request, $key] = $factory($item);
            $this->add($request, $key);
        }

        return $this;
    }

    /**
     * Attach this batch to a group so progress can be tracked across batches.
     *
     * Groups are a persisted feature, so this requires persistence.
     *
     * @throws BatchException When persistence is not enabled.
     */
    public function group(BatchGroup $group): static
    {
        if ($this->batchService === null) {
            throw BatchException::persistenceRequired();
        }

        $this->group = $group;

        return $this;
    }

    /**
     * Override the provider completion window (default "24h").
     */
    public function completionWindow(string $window): static
    {
        $this->completionWindow = $window;

        return $this;
    }

    /**
     * Submit the batch to the provider.
     *
     * When persistence is enabled, the batch is persisted as a {@see BatchJob}
     * (auto-polled by atlas:batch-poll). Otherwise it's stateless and returns a
     * {@see BatchResponse} with the provider batch id for you to poll.
     *
     * @throws BatchException When the batch is empty.
     */
    public function submit(): BatchResponse|BatchJob
    {
        if ($this->lines === [] || $this->provider === null || $this->modality === null) {
            throw BatchException::empty();
        }

        $batch = new Batch($this->provider, $this->modality, $this->lines, $this->completionWindow);

        if ($this->batchService !== null) {
            return $this->batchService->submitAndTrack($batch, $this->group);
        }

        return $this->registry->resolve($this->provider)->batch($batch);
    }

    /**
     * Number of requests currently in the batch.
     */
    public function count(): int
    {
        return count($this->lines);
    }

    /**
     * Fix or validate the batch's modality and provider against an added request.
     *
     * @throws BatchException
     */
    protected function bindModalityAndProvider(Batchable $request): void
    {
        $modality = $request->batchModality();
        $provider = $request->batchProvider();

        $this->modality ??= $modality;
        $this->provider ??= $provider;

        if ($modality !== $this->modality) {
            throw BatchException::mixedModality($this->modality->value, $modality->value);
        }

        if ($provider !== $this->provider) {
            throw BatchException::mixedProvider($this->provider, $provider);
        }
    }

    /**
     * Reject features that cannot survive a one-shot batch line.
     *
     * Tools require the synchronous model→tool→model loop, and per-request
     * middleware wraps the synchronous call — neither runs for a deferred batch
     * line, so attaching them is an explicit error rather than a silent no-op.
     * Everything that is part of the request body (messages, vision input,
     * structured-output schema, reasoning effort, provider options) is preserved.
     *
     * @throws BatchException
     */
    protected function assertNoLoopFeatures(object $dto): void
    {
        if (property_exists($dto, 'tools') && $dto->tools !== []) {
            throw BatchException::toolsUnsupported();
        }

        if (property_exists($dto, 'providerTools') && $dto->providerTools !== []) {
            throw BatchException::toolsUnsupported();
        }

        if (property_exists($dto, 'middleware') && $dto->middleware !== []) {
            throw BatchException::middlewareUnsupported();
        }
    }
}
