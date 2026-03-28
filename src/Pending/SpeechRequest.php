<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\HasProviderOptions;
use Atlasphp\Atlas\Pending\Concerns\HasQueueDispatch;
use Atlasphp\Atlas\Pending\Concerns\HasRequestConfig;
use Atlasphp\Atlas\Pending\Concerns\HasVariables;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Queue\Contracts\QueueableRequest;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Requests\AudioRequest as AudioRequestObject;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Str;

/**
 * Fluent builder for text-to-speech and speech-to-text requests.
 */
class SpeechRequest implements QueueableRequest
{
    use HasMeta;
    use HasMiddleware;
    use HasProviderOptions;
    use HasQueueDispatch;
    use HasRequestConfig;
    use HasVariables;
    use ResolvesProvider;

    protected ?string $instructions = null;

    /** @var array<int, mixed> */
    protected array $media = [];

    protected ?string $voice = null;

    /** @var array<string, mixed>|null */
    protected ?array $voiceClone = null;

    protected ?float $speed = null;

    protected ?string $language = null;

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

    /**
     * @param  array<int, mixed>  $media
     */
    public function withMedia(array $media): static
    {
        $this->media = $media;

        return $this;
    }

    public function withVoice(string $voice): static
    {
        $this->voice = $voice;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $voiceClone
     */
    public function withVoiceClone(array $voiceClone): static
    {
        $this->voiceClone = $voiceClone;

        return $this;
    }

    public function withSpeed(float $speed): static
    {
        $this->speed = $speed;

        return $this;
    }

    public function withLanguage(string $language): static
    {
        $this->language = $language;

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

        event(new ModalityStarted(modality: Modality::Speech, provider: $provider, model: $model, traceId: $traceId));

        try {
            $driver = $this->resolveDriver();
            $this->ensureCapability($driver, 'audio');
            $response = $driver->audio($this->buildRequest());
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Speech, provider: $provider, model: $model, traceId: $traceId));

            throw $e;
        }

        event(new ModalityCompleted(modality: Modality::Speech, provider: $provider, model: $model, traceId: $traceId));

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

        event(new ModalityStarted(modality: Modality::SpeechToText, provider: $provider, model: $model, traceId: $traceId));

        try {
            $driver = $this->resolveDriver();
            $this->ensureCapability($driver, 'audioToText');
            $response = $driver->audioToText($this->buildRequest());
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::SpeechToText, provider: $provider, model: $model, traceId: $traceId));

            throw $e;
        }

        event(new ModalityCompleted(modality: Modality::SpeechToText, provider: $provider, model: $model, usage: $response->usage, traceId: $traceId));

        return $response;
    }

    public function buildRequest(): AudioRequestObject
    {
        return new AudioRequestObject(
            model: $this->model ?? '',
            instructions: $this->interpolate($this->instructions),
            media: $this->media,
            voice: $this->voice,
            speed: $this->speed,
            language: $this->language,
            duration: null,
            format: $this->format,
            voiceClone: $this->voiceClone,
            providerOptions: $this->providerOptions,
            middleware: $this->middleware,
            meta: array_merge($this->meta, [
                '_audio_mode' => 'speech',
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
            'media' => $this->media,
            'voice' => $this->voice,
            'voiceClone' => $this->voiceClone,
            'speed' => $this->speed,
            'language' => $this->language,
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
     * @param  string  $terminal  Terminal method name (e.g. 'asAudio', 'asText')
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting
     */
    public static function executeFromPayload(
        array $payload,
        string $terminal,
        ?int $executionId = null,
        ?Channel $broadcastChannel = null,
    ): mixed {
        $request = Atlas::speech($payload['provider'], $payload['model']);

        if ($payload['instructions'] !== null) {
            $request->instructions($payload['instructions']);
        }

        if (! empty($payload['media'])) {
            $request->withMedia($payload['media']);
        }

        if ($payload['voice'] !== null) {
            $request->withVoice($payload['voice']);
        }

        if ($payload['voiceClone'] !== null) {
            $request->withVoiceClone($payload['voiceClone']);
        }

        if ($payload['speed'] !== null) {
            $request->withSpeed($payload['speed']);
        }

        if ($payload['language'] !== null) {
            $request->withLanguage($payload['language']);
        }

        if ($payload['format'] !== null) {
            $request->withFormat($payload['format']);
        }

        if (! empty($payload['providerOptions'])) {
            $request->withProviderOptions($payload['providerOptions']);
        }

        static::applyMeta($request, $payload, $executionId);
        static::applyVariables($request, $payload);

        return match ($terminal) {
            'asAudio' => $request->asAudio(),
            'asText' => $request->asText(),
            default => throw new \InvalidArgumentException("Unknown terminal method: {$terminal}"),
        };
    }

    /**
     * Resolve the execution type for speech requests.
     */
    protected function resolveExecutionType(string $terminal): ExecutionType
    {
        return match ($terminal) {
            'asAudio' => ExecutionType::Speech,
            'asText' => ExecutionType::AudioToText,
            default => ExecutionType::Speech,
        };
    }
}
