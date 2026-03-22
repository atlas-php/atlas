<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Concerns\HasQueueDispatch;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Queue\QueueableRequest;
use Atlasphp\Atlas\Requests\RerankRequest as RerankRequestObject;
use Atlasphp\Atlas\Responses\RerankResponse;
use Illuminate\Broadcasting\Channel;

/**
 * Fluent builder for reranking requests.
 */
class RerankRequest implements QueueableRequest
{
    use HasMeta;
    use HasMiddleware;
    use HasQueueDispatch;
    use ResolvesProvider;

    protected ?string $query = null;

    /** @var array<int, string|array<string, string>>|null */
    protected ?array $documents = null;

    protected ?int $topN = null;

    protected ?int $maxTokensPerDoc = null;

    protected ?float $minScore = null;

    /** @var array<string, mixed> */
    protected array $providerOptions = [];

    public function __construct(
        protected readonly Provider|string $provider,
        protected readonly ?string $model,
        protected readonly ProviderRegistryContract $registry,
    ) {}

    public function query(string $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @param  array<int, string|array<string, string>>  $documents
     */
    public function documents(array $documents): static
    {
        $this->documents = $documents;

        return $this;
    }

    public function topN(int $topN): static
    {
        $this->topN = $topN;

        return $this;
    }

    public function maxTokensPerDoc(int $maxTokensPerDoc): static
    {
        $this->maxTokensPerDoc = $maxTokensPerDoc;

        return $this;
    }

    public function minScore(float $minScore): static
    {
        $this->minScore = $minScore;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function withProviderOptions(array $options): static
    {
        $this->providerOptions = $options;

        return $this;
    }

    public function asReranked(): RerankResponse|PendingExecution
    {
        if ($this->query === null) {
            throw new \InvalidArgumentException('Query must be provided via query() before dispatching.');
        }

        if ($this->documents === null || $this->documents === []) {
            throw new \InvalidArgumentException('Documents must be provided via documents() before dispatching.');
        }

        if ($this->queued) {
            return $this->dispatchToQueue('asReranked');
        }

        $provider = $this->resolveProviderKey();
        $model = (string) $this->model;

        event(new ModalityStarted(modality: Modality::Rerank, provider: $provider, model: $model));

        try {
            $driver = $this->resolveDriver();
            $this->ensureCapability($driver, 'rerank');
            $response = $driver->rerank($this->buildRequest());
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Rerank, provider: $provider, model: $model));

            throw $e;
        }

        event(new ModalityCompleted(modality: Modality::Rerank, provider: $provider, model: $model));

        if ($this->minScore !== null) {
            return new RerankResponse($response->aboveScore($this->minScore), $response->meta);
        }

        return $response;
    }

    public function buildRequest(): RerankRequestObject
    {
        return new RerankRequestObject(
            model: $this->model ?? '',
            query: $this->query ?? '',
            documents: $this->documents ?? [],
            topN: $this->topN,
            maxTokensPerDoc: $this->maxTokensPerDoc,
            providerOptions: $this->providerOptions,
            middleware: $this->middleware,
            meta: $this->meta,
        );
    }

    /**
     * Serialize all properties needed to rebuild this request in a queue worker.
     *
     * @return array<string, mixed>
     */
    public function toQueuePayload(): array
    {
        return [
            'provider' => $this->resolveProviderKey(),
            'model' => $this->resolveModelKey(),
            'query' => $this->query,
            'documents' => $this->documents,
            'topN' => $this->topN,
            'maxTokensPerDoc' => $this->maxTokensPerDoc,
            'minScore' => $this->minScore,
            'providerOptions' => $this->providerOptions,
            'meta' => $this->meta,
        ];
    }

    /**
     * Rebuild this request from a queue payload and execute the given terminal method.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $terminal  Terminal method name (e.g. 'asReranked')
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting
     */
    public static function executeFromPayload(
        array $payload,
        string $terminal,
        ?int $executionId = null,
        ?Channel $broadcastChannel = null,
    ): mixed {
        $request = Atlas::rerank($payload['provider'], $payload['model']);

        if ($payload['query'] !== null) {
            $request->query($payload['query']);
        }

        if (! empty($payload['documents'])) {
            $request->documents($payload['documents']);
        }

        if ($payload['topN'] !== null) {
            $request->topN($payload['topN']);
        }

        if ($payload['maxTokensPerDoc'] !== null) {
            $request->maxTokensPerDoc($payload['maxTokensPerDoc']);
        }

        if ($payload['minScore'] !== null) {
            $request->minScore($payload['minScore']);
        }

        if (! empty($payload['providerOptions'])) {
            $request->withProviderOptions($payload['providerOptions']);
        }

        $meta = $payload['meta'] ?? [];

        if ($executionId !== null) {
            $meta['_execution_id'] = $executionId;
        }

        if (! empty($meta)) {
            $request->withMeta($meta);
        }

        return match ($terminal) {
            'asReranked' => $request->asReranked(),
            default => throw new \InvalidArgumentException("Unknown terminal method: {$terminal}"),
        };
    }

    /** Resolve the model as a string key for queue serialization. */
    protected function resolveModelKey(): string
    {
        return (string) $this->model;
    }
}
