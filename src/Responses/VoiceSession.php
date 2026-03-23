<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Enums\VoiceTransport;
use DateTimeImmutable;

/**
 * Response from creating a voice session.
 *
 * Contains the session ID, ephemeral token for browser-direct connection,
 * connection URL, session config, and endpoint URLs for tool execution
 * and transcript persistence.
 */
class VoiceSession
{
    /**
     * @param  array<string, mixed>  $sessionConfig
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $provider,
        public readonly string $model,
        public readonly VoiceTransport $transport,
        public readonly ?string $ephemeralToken = null,
        public readonly ?string $connectionUrl = null,
        public readonly ?string $clientSecret = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
        public readonly ?string $toolEndpoint = null,
        public readonly ?string $transcriptEndpoint = null,
        public readonly array $sessionConfig = [],
        public readonly array $meta = [],
    ) {}

    /**
     * Check if the session token has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt <= new DateTimeImmutable;
    }

    /**
     * Return a new instance with tool and transcript endpoint URLs set.
     */
    public function withEndpoints(?string $toolEndpoint, ?string $transcriptEndpoint): self
    {
        return new self(
            sessionId: $this->sessionId,
            provider: $this->provider,
            model: $this->model,
            transport: $this->transport,
            ephemeralToken: $this->ephemeralToken,
            connectionUrl: $this->connectionUrl,
            clientSecret: $this->clientSecret,
            expiresAt: $this->expiresAt,
            toolEndpoint: $toolEndpoint,
            transcriptEndpoint: $transcriptEndpoint,
            sessionConfig: $this->sessionConfig,
            meta: $this->meta,
        );
    }

    /**
     * Return a safe subset of session data for the browser client.
     *
     * Includes the session config so the browser can send it as
     * a session.update event on the direct connection.
     *
     * @return array<string, mixed>
     */
    public function toClientPayload(): array
    {
        return array_filter([
            'session_id' => $this->sessionId,
            'provider' => $this->provider,
            'model' => $this->model,
            'transport' => $this->transport->value,
            'ephemeral_token' => $this->ephemeralToken,
            'connection_url' => $this->connectionUrl,
            'expires_at' => $this->expiresAt?->format('c'),
            'tool_endpoint' => $this->toolEndpoint,
            'transcript_endpoint' => $this->transcriptEndpoint,
            'session_config' => $this->sessionConfig !== [] ? $this->sessionConfig : null,
        ], fn (mixed $v): bool => $v !== null);
    }
}
