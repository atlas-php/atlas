<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Concerns\HasQueueDispatch;
use Atlasphp\Atlas\Concerns\HasVariables;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Events\VideoCompleted;
use Atlasphp\Atlas\Events\VideoStarted;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Queue\QueueableRequest;
use Atlasphp\Atlas\Requests\VideoRequest as VideoRequestObject;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\VideoResponse;
use Illuminate\Broadcasting\Channel;

/**
 * Fluent builder for video generation and video-to-text requests.
 */
class VideoRequest implements QueueableRequest
{
    use HasMeta;
    use HasMiddleware;
    use HasQueueDispatch;
    use HasVariables;
    use ResolvesProvider;

    protected ?string $instructions = null;

    /** @var array<int, mixed> */
    protected array $media = [];

    protected ?int $duration = null;

    protected ?string $ratio = null;

    protected ?string $format = null;

    /** @var array<string, mixed> */
    protected array $providerOptions = [];

    public function __construct(
        protected readonly Provider|string $provider,
        protected readonly ?string $model,
        protected readonly ProviderRegistryContract $registry,
    ) {}

    public function instructions(string $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $media
     */
    public function withMedia(array $media): static
    {
        $this->media = $media;

        return $this;
    }

    public function withDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function withRatio(string $ratio): static
    {
        $this->ratio = $ratio;

        return $this;
    }

    public function withFormat(string $format): static
    {
        $this->format = $format;

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

    public function asVideo(): VideoResponse|PendingExecution
    {
        if ($this->queued) {
            return $this->dispatchToQueue('asVideo');
        }

        event(new VideoStarted(modality: Modality::Video, provider: $this->resolveProviderKey(), model: (string) $this->model));

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'video');

        $response = $driver->video($this->buildRequest());

        event(new VideoCompleted(modality: Modality::Video, provider: $this->resolveProviderKey(), model: (string) $this->model, usage: null));

        return $response;
    }

    public function asText(): TextResponse|PendingExecution
    {
        if ($this->queued) {
            return $this->dispatchToQueue('asText');
        }

        event(new VideoStarted(modality: Modality::VideoToText, provider: $this->resolveProviderKey(), model: (string) $this->model));

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'videoToText');

        $response = $driver->videoToText($this->buildRequest());

        event(new VideoCompleted(modality: Modality::VideoToText, provider: $this->resolveProviderKey(), model: (string) $this->model, usage: $response->usage));

        return $response;
    }

    public function buildRequest(): VideoRequestObject
    {
        return new VideoRequestObject(
            model: $this->model ?? '',
            instructions: $this->interpolate($this->instructions),
            media: $this->media,
            duration: $this->duration,
            ratio: $this->ratio,
            format: $this->format,
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
            'instructions' => $this->instructions,
            'media' => $this->media,
            'duration' => $this->duration,
            'ratio' => $this->ratio,
            'format' => $this->format,
            'providerOptions' => $this->providerOptions,
            'meta' => $this->meta,
            'variables' => $this->variables,
            'interpolate_messages' => $this->interpolateMessages,
        ];
    }

    /**
     * Rebuild this request from a queue payload and execute the given terminal method.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $terminal  Terminal method name (e.g. 'asVideo', 'asText')
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting
     */
    public static function executeFromPayload(
        array $payload,
        string $terminal,
        ?int $executionId = null,
        ?Channel $broadcastChannel = null,
    ): mixed {
        $request = Atlas::video($payload['provider'], $payload['model']);

        if ($payload['instructions'] !== null) {
            $request->instructions($payload['instructions']);
        }

        if (! empty($payload['media'])) {
            $request->withMedia($payload['media']);
        }

        if ($payload['duration'] !== null) {
            $request->withDuration($payload['duration']);
        }

        if ($payload['ratio'] !== null) {
            $request->withRatio($payload['ratio']);
        }

        if ($payload['format'] !== null) {
            $request->withFormat($payload['format']);
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

        if (! empty($payload['variables'])) {
            $request->withVariables($payload['variables']);
        }

        if ($payload['interpolate_messages'] ?? false) {
            $request->withMessageInterpolation();
        }

        return match ($terminal) {
            'asVideo' => $request->asVideo(),
            'asText' => $request->asText(),
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
