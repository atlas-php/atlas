<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

use Atlasphp\Atlas\Responses\VoiceEvent;
use WebSocket\Client;
use WebSocket\TimeoutException;

/**
 * Wraps a WebSocket client for voice provider communication.
 *
 * Provides typed send/receive of VoiceEvent objects over a persistent
 * WebSocket connection.
 */
class WebSocketConnection
{
    public function __construct(
        private readonly Client $client,
        public readonly string $sessionId,
    ) {}

    /**
     * Send a voice event over the WebSocket.
     *
     * Note: `type` and `event_id` are placed before the data spread,
     * so caller-supplied data keys named 'type' or 'event_id' will
     * take precedence. This is intentional — the caller's data is the
     * raw provider payload.
     */
    public function send(VoiceEvent $event): void
    {
        $payload = array_filter([
            'type' => $event->type,
            'event_id' => $event->eventId,
            ...$event->data,
        ], fn (mixed $v): bool => $v !== null);

        $this->client->text(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Receive the next event from the WebSocket, or null on timeout.
     *
     * Returns null only when no data is available (timeout). Connection-level
     * errors are re-thrown as WebSocket\ConnectionException or its subtypes.
     */
    public function receive(): ?VoiceEvent
    {
        try {
            $message = $this->client->receive();
            $raw = (string) $message;

            if ($raw === '') {
                return null;
            }

            /** @var array<string, mixed> $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $type = (string) ($data['type'] ?? 'unknown');
            $eventId = isset($data['event_id']) ? (string) $data['event_id'] : null;

            unset($data['type'], $data['event_id']);

            return new VoiceEvent(
                type: $type,
                eventId: $eventId,
                data: $data,
            );
        } catch (TimeoutException) {
            return null;
        } catch (\JsonException $e) {
            logger()->warning('[WebSocketConnection] Malformed JSON frame received', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Close the WebSocket connection.
     */
    public function close(): void
    {
        $this->client->close();
    }

    /**
     * Check if the WebSocket connection is still open.
     */
    public function isConnected(): bool
    {
        return $this->client->isConnected();
    }
}
