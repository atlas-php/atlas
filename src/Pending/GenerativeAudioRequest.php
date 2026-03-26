<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\HasProviderOptions;
use Atlasphp\Atlas\Pending\Concerns\HasQueueDispatch;
use Atlasphp\Atlas\Pending\Concerns\HasVariables;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Queue\QueueableRequestContract;
use Atlasphp\Atlas\Requests\AudioRequest as AudioRequestObject;
use Atlasphp\Atlas\Responses\AudioResponse;
use Illuminate\Support\Str;

/**
 * Abstract base for generative audio builders (music, sound effects).
 *
 * Subclasses define the modality, audio mode, and execution type while
 * inheriting all shared properties, fluent setters, and terminal logic.
 */
abstract class GenerativeAudioRequest implements QueueableRequestContract
{
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

    /**
     * The modality enum case for this builder (e.g. Modality::Music).
     */
    abstract protected function modality(): Modality;

    /**
     * The audio mode tag written to request meta (e.g. 'music', 'sfx').
     */
    abstract protected function audioMode(): string;

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

        event(new ModalityStarted(modality: $this->modality(), provider: $provider, model: $model, traceId: $traceId));

        try {
            $driver = $this->resolveDriver();
            $this->ensureCapability($driver, 'audio');
            $response = $driver->audio($this->buildRequest());
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: $this->modality(), provider: $provider, model: $model, traceId: $traceId));

            throw $e;
        }

        event(new ModalityCompleted(modality: $this->modality(), provider: $provider, model: $model, traceId: $traceId));

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
                '_audio_mode' => $this->audioMode(),
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
     * Rebuild common properties from a queue payload onto a request instance.
     *
     * Subclasses call this from their static executeFromPayload() after
     * creating the concrete request via the Atlas facade.
     *
     * @param  array<string, mixed>  $payload
     */
    protected static function applyPayload(self $request, array $payload, ?int $executionId): void
    {
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

        static::applyMeta($request, $payload, $executionId);
        static::applyVariables($request, $payload);
    }
}
