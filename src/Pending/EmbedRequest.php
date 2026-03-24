<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Concerns\HasQueueDispatch;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Pending\Concerns\AppliesQueueMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\HasProviderOptions;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Queue\QueueableRequest;
use Atlasphp\Atlas\Requests\EmbedRequest as EmbedRequestObject;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Illuminate\Broadcasting\Channel;

/**
 * Fluent builder for embedding requests.
 */
class EmbedRequest implements QueueableRequest
{
    use AppliesQueueMeta;
    use HasMeta;
    use HasMiddleware;
    use HasProviderOptions;
    use HasQueueDispatch;
    use ResolvesProvider;

    /** @var string|array<int, string>|null */
    protected string|array|null $input = null;

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

    public function asEmbeddings(): EmbeddingsResponse|PendingExecution
    {
        if ($this->input === null) {
            throw new \InvalidArgumentException('Input must be provided via fromInput() before dispatching.');
        }

        if ($this->queued) {
            return $this->dispatchToQueue('asEmbeddings');
        }

        $provider = $this->resolveProviderKey();
        $model = (string) $this->model;

        event(new ModalityStarted(modality: Modality::Embed, provider: $provider, model: $model));

        try {
            $driver = $this->resolveDriver();
            $this->ensureCapability($driver, 'embed');
            $response = $driver->embed($this->buildRequest());
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Embed, provider: $provider, model: $model));

            throw $e;
        }

        event(new ModalityCompleted(modality: Modality::Embed, provider: $provider, model: $model, usage: $response->usage));

        return $response;
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
            'input' => $this->input,
            'providerOptions' => $this->providerOptions,
            'meta' => $this->meta,
        ];
    }

    /**
     * Rebuild this request from a queue payload and execute the given terminal method.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $terminal  Terminal method name (e.g. 'asEmbeddings')
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting
     */
    public static function executeFromPayload(
        array $payload,
        string $terminal,
        ?int $executionId = null,
        ?Channel $broadcastChannel = null,
    ): mixed {
        $request = Atlas::embed($payload['provider'], $payload['model'])
            ->fromInput($payload['input']);

        if (! empty($payload['providerOptions'])) {
            $request->withProviderOptions($payload['providerOptions']);
        }

        static::applyQueueMeta($request, $payload, $executionId);

        return match ($terminal) {
            'asEmbeddings' => $request->asEmbeddings(),
            default => throw new \InvalidArgumentException("Unknown terminal method: {$terminal}"),
        };
    }
}
