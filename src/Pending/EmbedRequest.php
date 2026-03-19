<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Requests\EmbedRequest as EmbedRequestObject;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;

/**
 * Fluent builder for embedding requests.
 */
class EmbedRequest
{
    use HasMeta;
    use HasMiddleware;
    use ResolvesProvider;

    /** @var string|array<int, string>|null */
    protected string|array|null $input = null;

    /** @var array<string, mixed> */
    protected array $providerOptions = [];

    public function __construct(
        protected readonly Provider|string $provider,
        protected readonly ?string $model,
        protected readonly ProviderRegistryContract $registry,
    ) {}

    /**
     * @param  string|array<int, string>  $input
     */
    public function fromInput(string|array $input): static
    {
        $this->input = $input;

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

    public function asEmbeddings(): EmbeddingsResponse
    {
        if ($this->input === null) {
            throw new \InvalidArgumentException('Input must be provided via fromInput() before dispatching.');
        }

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'embed');

        return $driver->embed($this->buildRequest());
    }

    public function buildRequest(): EmbedRequestObject
    {
        return new EmbedRequestObject(
            model: $this->model ?? '',
            input: $this->input ?? '',
            providerOptions: $this->providerOptions,
            middleware: $this->middleware,
            meta: $this->meta,
        );
    }
}
