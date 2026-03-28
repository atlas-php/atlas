<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Enums\TurnDetectionMode;
use Atlasphp\Atlas\Enums\VoiceTransport;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Events\VoiceSessionCreated;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\HasProviderOptions;
use Atlasphp\Atlas\Pending\Concerns\HasRequestConfig;
use Atlasphp\Atlas\Pending\Concerns\HasVariables;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Requests\VoiceRequest as VoiceRequestObject;
use Atlasphp\Atlas\Responses\VoiceSession;
use Illuminate\Support\Str;

/**
 * Fluent builder for voice session requests.
 */
class VoiceRequest
{
    use HasMeta;
    use HasMiddleware;
    use HasProviderOptions;
    use HasRequestConfig;
    use HasVariables;
    use ResolvesProvider;

    protected ?string $instructions = null;

    protected ?string $voice = null;

    protected VoiceTransport $transport = VoiceTransport::WebSocket;

    protected TurnDetectionMode $turnDetection = TurnDetectionMode::ServerVad;

    protected ?float $vadThreshold = null;

    protected ?int $vadSilenceDuration = null;

    protected ?string $inputAudioFormat = null;

    protected ?string $outputAudioFormat = null;

    protected ?float $temperature = null;

    protected ?int $maxResponseTokens = null;

    protected ?string $inputAudioTranscription = null;

    /** @var array<int, mixed> */
    protected array $tools = [];

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

    public function withVoice(string $voice): static
    {
        $this->voice = $voice;

        return $this;
    }

    public function viaWebRtc(): static
    {
        $this->transport = VoiceTransport::WebRtc;

        return $this;
    }

    public function viaWebSocket(): static
    {
        $this->transport = VoiceTransport::WebSocket;

        return $this;
    }

    public function withServerVad(?float $threshold = null, ?int $silenceDuration = null): static
    {
        $this->turnDetection = TurnDetectionMode::ServerVad;
        $this->vadThreshold = $threshold;
        $this->vadSilenceDuration = $silenceDuration;

        return $this;
    }

    public function withManualTurnDetection(): static
    {
        $this->turnDetection = TurnDetectionMode::Manual;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $tools
     */
    public function withTools(array $tools): static
    {
        $this->tools = $tools;

        return $this;
    }

    public function withTemperature(float $temperature): static
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function withMaxResponseTokens(int $maxResponseTokens): static
    {
        $this->maxResponseTokens = $maxResponseTokens;

        return $this;
    }

    public function withInputFormat(string $format): static
    {
        $this->inputAudioFormat = $format;

        return $this;
    }

    public function withOutputFormat(string $format): static
    {
        $this->outputAudioFormat = $format;

        return $this;
    }

    /**
     * Enable input audio transcription so the provider sends user speech transcripts.
     */
    public function withInputTranscription(string $model = 'whisper-1'): static
    {
        $this->inputAudioTranscription = $model;

        return $this;
    }

    public function createSession(): VoiceSession
    {
        $traceId = (string) Str::uuid();
        $provider = $this->resolveProviderKey();
        $model = (string) $this->model;

        event(new ModalityStarted(modality: Modality::Voice, provider: $provider, model: $model, traceId: $traceId));

        try {
            $driver = $this->resolveDriver();
            $this->ensureCapability($driver, 'voice');
            $session = $driver->createVoiceSession($this->buildRequest());
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Voice, provider: $provider, model: $model, traceId: $traceId));

            throw $e;
        }

        event(new VoiceSessionCreated(
            provider: $provider,
            model: $model,
            sessionId: $session->sessionId,
            transport: $session->transport,
        ));

        event(new ModalityCompleted(modality: Modality::Voice, provider: $provider, model: $model, traceId: $traceId));

        return $session;
    }

    public function buildRequest(): VoiceRequestObject
    {
        return new VoiceRequestObject(
            model: $this->model ?? '',
            instructions: $this->interpolate($this->instructions),
            voice: $this->voice,
            transport: $this->transport,
            turnDetection: $this->turnDetection,
            vadThreshold: $this->vadThreshold,
            vadSilenceDuration: $this->vadSilenceDuration,
            inputAudioFormat: $this->inputAudioFormat,
            outputAudioFormat: $this->outputAudioFormat,
            temperature: $this->temperature,
            maxResponseTokens: $this->maxResponseTokens,
            inputAudioTranscription: $this->inputAudioTranscription,
            tools: $this->tools,
            providerOptions: $this->providerOptions,
            middleware: $this->middleware,
            meta: $this->meta,
        );
    }
}
