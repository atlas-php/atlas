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
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Queue\QueueableRequest;
use Atlasphp\Atlas\Requests\AudioRequest as AudioRequestObject;
use Atlasphp\Atlas\Responses\AudioResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Str;

/**
 * Fluent builder for sound effect generation requests.
 */
class SfxRequest implements QueueableRequest
{
    use AppliesQueueMeta;
    use HasMeta;
    use HasMiddleware;
    use HasProviderOptions;
    use HasQueueDispatch;
    use HasVariables;
    use ResolvesProvider;

    protected ?string $instructions = null;

    protected ?int $duration = null;

    protected ?string $format = null;

    public function __construct(
        protected readonly Provider|string|null $provider,
        protected readonly ?string $model,
        protected readonly ProviderRegistryContract $registry,
    ) {}

    public function instructions(string $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

    public function withDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function withFormat(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function asAudio(): AudioResponse|PendingExecution
    {
        $traceId = (string) Str::uuid();

        if ($this->queued) {
            return $this->dispatchToQueue('asAudio');
        }

        $provider = $this->resolveProviderKey();
        $model = (string) $this->model;

        event(new ModalityStarted(modality: Modality::Sfx, provider: $provider, model: $model, traceId: $traceId));

        try {
            $driver = $this->resolveDriver();
            $this->ensureCapability($driver, 'audio');
            $response = $driver->audio($this->buildRequest());
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Sfx, provider: $provider, model: $model, traceId: $traceId));

            throw $e;
        }

        event(new ModalityCompleted(modality: Modality::Sfx, provider: $provider, model: $model, traceId: $traceId));

        return $response;
    }

    public function buildRequest(): AudioRequestObject
    {
        return new AudioRequestObject(
            model: $this->model ?? '',
            instructions: $this->interpolate($this->instructions),
            media: [],
            voice: null,
            speed: null,
            language: null,
            duration: $this->duration,
            format: $this->format,
            voiceClone: null,
            providerOptions: $this->providerOptions,
            middleware: $this->middleware,
            meta: array_merge($this->meta, [
                '_audio_mode' => 'sfx',
            ]),
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
            'duration' => $this->duration,
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
     * @param  string  $terminal  Terminal method name (e.g. 'asAudio')
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting
     */
    public static function executeFromPayload(
        array $payload,
        string $terminal,
        ?int $executionId = null,
        ?Channel $broadcastChannel = null,
    ): mixed {
        $request = Atlas::sfx($payload['provider'], $payload['model']);

        if ($payload['instructions'] !== null) {
            $request->instructions($payload['instructions']);
        }

        if ($payload['duration'] !== null) {
            $request->withDuration($payload['duration']);
        }

        if ($payload['format'] !== null) {
            $request->withFormat($payload['format']);
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
            'asAudio' => $request->asAudio(),
            default => throw new \InvalidArgumentException("Unknown terminal method: {$terminal}"),
        };
    }

    /**
     * Resolve the execution type — always Sfx regardless of terminal.
     */
    protected function resolveExecutionType(string $terminal): ExecutionType
    {
        return ExecutionType::Sfx;
    }
}
