<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Enums\RealtimeTransport;
use DateTimeImmutable;

/**
 * Response from creating a realtime session.
 *
 * Contains the session ID, ephemeral token (for WebRTC), connection URL
 * (for WebSocket), and provider-specific session configuration.
 */
class RealtimeSession
{
    /**
     * @param  array<string, mixed>  $sessionConfig
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $provider,
        public readonly string $model,
        public readonly RealtimeTransport $transport,
        public readonly ?string $ephemeralToken = null,
        public readonly ?string $connectionUrl = null,
        public readonly ?string $clientSecret = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
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
     * Return a safe subset of session data for the browser client.
     *
     * Excludes sensitive fields like clientSecret that should not
     * be exposed to the frontend beyond the ephemeral token.
     *
     * @return array<string, mixed>
     */
    /**
     * Return a safe subset of session data for the browser client.
     *
     * Excludes sensitive fields like clientSecret that should not
     * be exposed to the frontend beyond the ephemeral token.
     *
     * @param  string|null  $transcriptEndpoint  URL for transcript persistence (set by consumer or package route)
     * @return array<string, mixed>
     */
    public function toClientPayload(?string $transcriptEndpoint = null): array
    {
        return array_filter([
            'session_id' => $this->sessionId,
            'provider' => $this->provider,
            'model' => $this->model,
            'transport' => $this->transport->value,
            'ephemeral_token' => $this->ephemeralToken,
            'connection_url' => $this->connectionUrl,
            'expires_at' => $this->expiresAt?->format('c'),
            'transcript_endpoint' => $transcriptEndpoint,
        ], fn (mixed $v): bool => $v !== null);
    }
}
