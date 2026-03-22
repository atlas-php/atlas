<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Concerns\HasQueueDispatch;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Events\ModerationCompleted;
use Atlasphp\Atlas\Events\ModerationStarted;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Queue\QueueableRequest;
use Atlasphp\Atlas\Requests\ModerateRequest as ModerateRequestObject;
use Atlasphp\Atlas\Responses\ModerationResponse;
use Illuminate\Broadcasting\Channel;

/**
 * Fluent builder for content moderation requests.
 */
class ModerateRequest implements QueueableRequest
{
    use HasMeta;
    use HasMiddleware;
    use HasQueueDispatch;
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

    public function asModeration(): ModerationResponse|PendingExecution
    {
        if ($this->input === null) {
            throw new \InvalidArgumentException('Input must be provided via fromInput() before dispatching.');
        }

        if ($this->queued) {
            return $this->dispatchToQueue('asModeration');
        }

        event(new ModerationStarted(modality: Modality::Moderate, provider: $this->resolveProviderKey(), model: (string) ($this->model ?? '')));

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'moderate');

        $response = $driver->moderate($this->buildRequest());

        event(new ModerationCompleted(modality: Modality::Moderate, provider: $this->resolveProviderKey(), model: (string) ($this->model ?? ''), usage: null));

        return $response;
    }

    public function buildRequest(): ModerateRequestObject
    {
        return new ModerateRequestObject(
            model: $this->model,
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
     * @param  string  $terminal  Terminal method name (e.g. 'asModeration')
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting
     */
    public static function executeFromPayload(
        array $payload,
        string $terminal,
        ?int $executionId = null,
        ?Channel $broadcastChannel = null,
    ): mixed {
        $request = Atlas::moderate($payload['provider'], $payload['model'])
            ->fromInput($payload['input']);

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
            'asModeration' => $request->asModeration(),
            default => throw new \InvalidArgumentException("Unknown terminal method: {$terminal}"),
        };
    }

    /**
     * Resolve the provider as a string key for queue serialization.
     */
    protected function resolveProviderKey(): string
    {
        return Provider::normalize($this->provider);
    }

    /**
     * Resolve the model as a string key for queue serialization.
     */
    protected function resolveModelKey(): string
    {
        return (string) $this->model;
    }
}
