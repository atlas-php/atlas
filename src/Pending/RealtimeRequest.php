<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Concerns\HasVariables;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Enums\RealtimeTransport;
use Atlasphp\Atlas\Enums\TurnDetectionMode;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Events\RealtimeSessionCreated;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Requests\RealtimeRequest as RealtimeRequestObject;
use Atlasphp\Atlas\Responses\RealtimeSession;

/**
 * Fluent builder for realtime voice-to-voice session requests.
 */
class RealtimeRequest
{
    use HasMeta;
    use HasMiddleware;
    use HasVariables;
    use ResolvesProvider;

    protected ?string $instructions = null;

    protected ?string $voice = null;

    protected RealtimeTransport $transport = RealtimeTransport::WebRtc;

    protected TurnDetectionMode $turnDetection = TurnDetectionMode::ServerVad;

    protected ?float $vadThreshold = null;

    protected ?int $vadSilenceDuration = null;

    protected ?string $inputAudioFormat = null;

    protected ?string $outputAudioFormat = null;

    protected ?float $temperature = null;

    protected ?int $maxResponseTokens = null;

    /** @var array<int, mixed> */
    protected array $tools = [];

    /** @var array<string, mixed> */
    protected array $providerOptions = [];

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
        $this->transport = RealtimeTransport::WebRtc;

        return $this;
    }

    public function viaWebSocket(): static
    {
        $this->transport = RealtimeTransport::WebSocket;

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
     * @param  array<string, mixed>  $options
     */
    public function withProviderOptions(array $options): static
    {
        $this->providerOptions = $options;

        return $this;
    }

    public function createSession(): RealtimeSession
    {
        $provider = $this->resolveProviderKey();
        $model = (string) $this->model;

        event(new ModalityStarted(modality: Modality::Realtime, provider: $provider, model: $model));

        try {
            $driver = $this->resolveDriver();
            $this->ensureCapability($driver, 'realtime');
            $session = $driver->createRealtimeSession($this->buildRequest());
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Realtime, provider: $provider, model: $model));

            throw $e;
        }

        event(new RealtimeSessionCreated(
            provider: $provider,
            model: $model,
            sessionId: $session->sessionId,
            transport: $session->transport,
        ));

        event(new ModalityCompleted(modality: Modality::Realtime, provider: $provider, model: $model));

        return $session;
    }

    public function buildRequest(): RealtimeRequestObject
    {
        return new RealtimeRequestObject(
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
            tools: $this->tools,
            providerOptions: $this->providerOptions,
            middleware: $this->middleware,
            meta: $this->meta,
        );
    }
}
