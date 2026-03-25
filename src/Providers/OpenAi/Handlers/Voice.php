<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Enums\VoiceTransport;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Concerns\BuildsVoiceBody;
use Atlasphp\Atlas\Providers\Handlers\VoiceHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Concerns\HasOrganizationHeader;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\WebSocketConnection;
use Atlasphp\Atlas\Requests\VoiceRequest;
use Atlasphp\Atlas\Responses\VoiceSession;
use DateTimeImmutable;
use WebSocket\Client;

/**
 * OpenAI voice handler for voice-to-voice sessions.
 *
 * WebRTC mode: POST /v1/realtime/sessions to get an ephemeral token.
 * WebSocket mode: Connect to wss://api.openai.com/v1/realtime?model={model}.
 */
class Voice implements VoiceHandler
{
    use BuildsHeaders, BuildsVoiceBody, HasOrganizationHeader {
        HasOrganizationHeader::extraHeaders insteadof BuildsHeaders;
    }

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function createSession(VoiceRequest $request): VoiceSession
    {
        if ($request->transport === VoiceTransport::WebRtc) {
            return $this->createWebRtcSession($request);
        }

        return $this->createWebSocketSession($request);
    }

    public function connect(VoiceSession $session): WebSocketConnection
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

    private function createWebRtcSession(VoiceRequest $request): VoiceSession
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

        return new VoiceSession(
            sessionId: $sessionId,
            provider: 'openai',
            model: $request->model,
            transport: VoiceTransport::WebRtc,
            ephemeralToken: $clientSecret,
            clientSecret: $clientSecret,
            expiresAt: $expiresAt,
            sessionConfig: $sessionConfig,
        );
    }

    private function createWebSocketSession(VoiceRequest $request): VoiceSession
    {
        $sessionId = 'rt_ws_'.bin2hex(random_bytes(16));
        $connectionUrl = $this->buildWebSocketUrl($request->model);

        return new VoiceSession(
            sessionId: $sessionId,
            provider: 'openai',
            model: $request->model,
            transport: VoiceTransport::WebSocket,
            connectionUrl: $connectionUrl,
            sessionConfig: $this->buildSessionBody($request, 'alloy'),
        );
    }

    private function buildWebSocketUrl(string $model): string
    {
        return $this->toWebSocketUrl($this->config->baseUrl)."/realtime?model={$model}";
    }
}
