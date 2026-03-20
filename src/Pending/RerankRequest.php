<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Requests\RerankRequest as RerankRequestObject;
use Atlasphp\Atlas\Responses\RerankResponse;

/**
 * Fluent builder for reranking requests.
 */
class RerankRequest
{
    use HasMeta;
    use HasMiddleware;
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

    public function asReranked(): RerankResponse
    {
        if ($this->query === null) {
            throw new \InvalidArgumentException('Query must be provided via query() before dispatching.');
        }

        if ($this->documents === null || $this->documents === []) {
            throw new \InvalidArgumentException('Documents must be provided via documents() before dispatching.');
        }

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'rerank');

        $response = $driver->rerank($this->buildRequest());

        if ($this->minScore !== null) {
            $filtered = $response->aboveScore($this->minScore);

            return new RerankResponse($filtered, $response->meta);
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
}
