<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Enums\RealtimeTransport;
use Atlasphp\Atlas\Responses\RealtimeSession;
use DateTimeImmutable;

/**
 * Fluent builder for creating fake RealtimeSession objects in tests.
 */
class RealtimeSessionFake
{
    protected string $sessionId;

    protected string $provider = 'openai';

    protected string $model = 'gpt-4o-realtime-preview';

    protected RealtimeTransport $transport = RealtimeTransport::WebRtc;

    protected ?string $ephemeralToken = 'fake-ephemeral-token';

    protected ?string $connectionUrl = null;

    protected ?string $clientSecret = 'fake-client-secret';

    protected ?DateTimeImmutable $expiresAt = null;

    /** @var array<string, mixed> */
    protected array $sessionConfig = [];

    /** @var array<string, mixed> */
    protected array $meta = [];

    public function __construct()
    {
        $this->sessionId = 'fake_rt_'.uniqid();
        $this->expiresAt = (new DateTimeImmutable)->modify('+60 seconds');
    }

    public static function make(): self
    {
        return new self;
    }

    public function withSessionId(string $sessionId): static
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function withProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function withModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function withTransport(RealtimeTransport $transport): static
    {
        $this->transport = $transport;

        return $this;
    }

    public function withEphemeralToken(?string $token): static
    {
        $this->ephemeralToken = $token;

        return $this;
    }

    public function withConnectionUrl(?string $url): static
    {
        $this->connectionUrl = $url;

        return $this;
    }

    public function withClientSecret(?string $clientSecret): static
    {
        $this->clientSecret = $clientSecret;

        return $this;
    }

    public function withExpiresAt(?DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function toResponse(): RealtimeSession
    {
        return new RealtimeSession(
            sessionId: $this->sessionId,
            provider: $this->provider,
            model: $this->model,
            transport: $this->transport,
            ephemeralToken: $this->ephemeralToken,
            connectionUrl: $this->connectionUrl,
            clientSecret: $this->clientSecret,
            expiresAt: $this->expiresAt,
            sessionConfig: $this->sessionConfig,
            meta: $this->meta,
        );
    }
}
