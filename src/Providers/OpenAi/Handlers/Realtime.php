<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Enums\RealtimeTransport;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Concerns\BuildsRealtimeBody;
use Atlasphp\Atlas\Providers\Handlers\RealtimeHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\HasOrganizationHeader;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\WebSocketConnection;
use Atlasphp\Atlas\Requests\RealtimeRequest;
use Atlasphp\Atlas\Responses\RealtimeSession;
use DateTimeImmutable;
use WebSocket\Client;

/**
 * OpenAI realtime handler for voice-to-voice sessions.
 *
 * WebRTC mode: POST /v1/realtime/sessions to get an ephemeral token.
 * WebSocket mode: Connect to wss://api.openai.com/v1/realtime?model={model}.
 */
class Realtime implements RealtimeHandler
{
    use BuildsHeaders, BuildsRealtimeBody, HasOrganizationHeader {
        HasOrganizationHeader::extraHeaders insteadof BuildsHeaders;
    }

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function createSession(RealtimeRequest $request): RealtimeSession
    {
        if ($request->transport === RealtimeTransport::WebRtc) {
            return $this->createWebRtcSession($request);
        }

        return $this->createWebSocketSession($request);
    }

    public function connect(RealtimeSession $session): WebSocketConnection
    {
        $url = $session->connectionUrl ?? $this->buildWebSocketUrl($session->model);

        $client = new Client($url, [
            'headers' => [
                'Authorization' => "Bearer {$this->config->apiKey}",
                'OpenAI-Beta' => 'realtime=v1',
            ],
        ]);

        return new WebSocketConnection($client, $session->sessionId);
    }

    private function createWebRtcSession(RealtimeRequest $request): RealtimeSession
    {
        $body = $this->buildSessionBody($request, 'alloy');

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/realtime/sessions",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
        );

        $sessionId = (string) ($data['id'] ?? 'rt_'.bin2hex(random_bytes(16)));
        $clientSecret = $data['client_secret']['value'] ?? null;
        $expiresAt = isset($data['client_secret']['expires_at'])
            ? (new DateTimeImmutable)->setTimestamp((int) $data['client_secret']['expires_at'])
            : null;

        // Strip client_secret from stored config to prevent accidental leaks
        $sessionConfig = $data;
        unset($sessionConfig['client_secret']);

        return new RealtimeSession(
            sessionId: $sessionId,
            provider: 'openai',
            model: $request->model,
            transport: RealtimeTransport::WebRtc,
            ephemeralToken: $clientSecret,
            clientSecret: $clientSecret,
            expiresAt: $expiresAt,
            sessionConfig: $sessionConfig,
        );
    }

    private function createWebSocketSession(RealtimeRequest $request): RealtimeSession
    {
        $sessionId = 'rt_ws_'.bin2hex(random_bytes(16));
        $connectionUrl = $this->buildWebSocketUrl($request->model);

        return new RealtimeSession(
            sessionId: $sessionId,
            provider: 'openai',
            model: $request->model,
            transport: RealtimeTransport::WebSocket,
            connectionUrl: $connectionUrl,
            sessionConfig: $this->buildSessionBody($request, 'alloy'),
        );
    }

    private function buildWebSocketUrl(string $model): string
    {
        return $this->toWebSocketUrl($this->config->baseUrl)."/realtime?model={$model}";
    }
}
