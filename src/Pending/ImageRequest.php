<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Concerns\HasQueueDispatch;
use Atlasphp\Atlas\Concerns\HasVariables;
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
use Atlasphp\Atlas\Queue\QueueableRequestContract;
use Atlasphp\Atlas\Requests\ImageRequest as ImageRequestObject;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Str;

/**
 * Fluent builder for image generation and image-to-text requests.
 */
class ImageRequest implements QueueableRequestContract
{
    use AppliesQueueMeta;
    use HasMeta;
    use HasMiddleware;
    use HasProviderOptions;
    use HasQueueDispatch;
    use HasVariables;
    use ResolvesProvider;

    protected ?string $instructions = null;

    /** @var array<int, mixed> */
    protected array $media = [];

    protected ?string $size = null;

    protected ?string $quality = null;

    protected ?string $format = null;

    protected int $count = 1;

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

    public function withSize(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function withQuality(string $quality): static
    {
        $this->quality = $quality;

        return $this;
    }

    public function withFormat(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function withCount(int $count): static
    {
        $this->count = $count;

        return $this;
    }

    public function asImage(): ImageResponse|PendingExecution
    {
        $traceId = (string) Str::uuid();

        if ($this->queued) {
            return $this->dispatchToQueue('asImage');
        }

        $provider = $this->resolveProviderKey();
        $model = (string) $this->model;

        event(new ModalityStarted(modality: Modality::Image, provider: $provider, model: $model, traceId: $traceId));

        try {
            $driver = $this->resolveDriver();
            $this->ensureCapability($driver, 'image');
            $response = $driver->image($this->buildRequest());
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Image, provider: $provider, model: $model, traceId: $traceId));

            throw $e;
        }

        event(new ModalityCompleted(modality: Modality::Image, provider: $provider, model: $model, traceId: $traceId));

        return $response;
    }

    public function asText(): TextResponse|PendingExecution
    {
        $traceId = (string) Str::uuid();

        if ($this->queued) {
            return $this->dispatchToQueue('asText');
        }

        $provider = $this->resolveProviderKey();
        $model = (string) $this->model;

        event(new ModalityStarted(modality: Modality::ImageToText, provider: $provider, model: $model, traceId: $traceId));

        try {
            $driver = $this->resolveDriver();
            $this->ensureCapability($driver, 'imageToText');
            $response = $driver->imageToText($this->buildRequest());
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::ImageToText, provider: $provider, model: $model, traceId: $traceId));

            throw $e;
        }

        event(new ModalityCompleted(modality: Modality::ImageToText, provider: $provider, model: $model, usage: $response->usage, traceId: $traceId));

        return $response;
    }

    public function buildRequest(): ImageRequestObject
    {
        return new ImageRequestObject(
            model: $this->model ?? '',
            instructions: $this->interpolate($this->instructions),
            media: $this->media,
            size: $this->size,
            quality: $this->quality,
            format: $this->format,
            providerOptions: $this->providerOptions,
            count: $this->count,
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
            'size' => $this->size,
            'quality' => $this->quality,
            'format' => $this->format,
            'count' => $this->count,
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
     * @param  string  $terminal  Terminal method name (e.g. 'asImage', 'asText')
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting
     */
    public static function executeFromPayload(
        array $payload,
        string $terminal,
        ?int $executionId = null,
        ?Channel $broadcastChannel = null,
    ): mixed {
        $request = Atlas::image($payload['provider'], $payload['model']);

        if ($payload['instructions'] !== null) {
            $request->instructions($payload['instructions']);
        }

        if (! empty($payload['media'])) {
            $request->withMedia($payload['media']);
        }

        if ($payload['size'] !== null) {
            $request->withSize($payload['size']);
        }

        if ($payload['quality'] !== null) {
            $request->withQuality($payload['quality']);
        }

        if ($payload['format'] !== null) {
            $request->withFormat($payload['format']);
        }

        if (array_key_exists('count', $payload)) {
            $request->withCount($payload['count']);
        }

        if (! empty($payload['providerOptions'])) {
            $request->withProviderOptions($payload['providerOptions']);
        }

        static::applyQueueMeta($request, $payload, $executionId);

        if (! empty($payload['variables'])) {
            $request->withVariables($payload['variables']);
        }

        if ($payload['interpolate_messages'] ?? false) {
            $request->withMessageInterpolation();
        }

        return match ($terminal) {
            'asImage' => $request->asImage(),
            'asText' => $request->asText(),
            default => throw new \InvalidArgumentException("Unknown terminal method: {$terminal}"),
        };
    }
}
