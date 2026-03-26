<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai\Handlers;

use Atlasphp\Atlas\Enums\VoiceTransport;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Concerns\BuildsVoiceBody;
use Atlasphp\Atlas\Providers\Handlers\VoiceHandler;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\WebSocketConnection;
use Atlasphp\Atlas\Requests\VoiceRequest;
use Atlasphp\Atlas\Responses\VoiceSession;
use WebSocket\Client;

/**
 * xAI voice handler for voice-to-voice sessions.
 *
 * Uses ephemeral tokens from POST /v1/realtime/client_secrets
 * for browser-direct WebSocket connections.
 */
class Voice implements VoiceHandler
{
    use BuildsHeaders;
    use BuildsVoiceBody;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function createSession(VoiceRequest $request): VoiceSession
    {
        $sessionId = 'rt_xai_'.bin2hex(random_bytes(16));
        $connectionUrl = $this->toWebSocketUrl($this->config->baseUrl).'/realtime';
        $sessionConfig = $this->buildSessionBody($request, 'eve');

        // Get ephemeral token for browser-direct connection
        $data = $this->http->post(
            url: "{$this->config->baseUrl}/realtime/client_secrets",
            headers: $this->headers(),
            body: $sessionConfig,
            timeout: $this->config->timeout,
        );

        $ephemeralToken = $data['value'] ?? $data['client_secret'] ?? null;

        return new VoiceSession(
            sessionId: $sessionId,
            provider: 'xai',
            model: $request->model,
            transport: VoiceTransport::WebSocket,
            ephemeralToken: $ephemeralToken,
            connectionUrl: $connectionUrl,
            sessionConfig: $sessionConfig,
        );
    }

    public function connect(VoiceSession $session): WebSocketConnection
    {
        $client = new Client($session->connectionUrl, [
            'headers' => [
                'Authorization' => "Bearer {$this->config->apiKey}",
            ],
        ]);

        return new WebSocketConnection($client, $session->sessionId);
    }
}
