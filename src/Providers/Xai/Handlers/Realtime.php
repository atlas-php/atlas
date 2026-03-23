<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai\Handlers;

use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Concerns\BuildsRealtimeBody;
use Atlasphp\Atlas\Providers\Handlers\RealtimeHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\WebSocketConnection;
use Atlasphp\Atlas\Requests\RealtimeRequest;
use Atlasphp\Atlas\Responses\RealtimeSession;
use WebSocket\Client;

/**
 * xAI realtime handler for voice-to-voice sessions.
 *
 * xAI uses a WebSocket-only realtime API at wss://api.x.ai/v1/realtime.
 * Audio formats: PCM16/G.711 at 8-48kHz.
 */
class Realtime implements RealtimeHandler
{
    use BuildsHeaders;
    use BuildsRealtimeBody;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function createSession(RealtimeRequest $request): RealtimeSession
    {
        $sessionId = 'rt_xai_'.bin2hex(random_bytes(16));
        $connectionUrl = $this->toWebSocketUrl($this->config->baseUrl).'/realtime';

        return new RealtimeSession(
            sessionId: $sessionId,
            provider: 'xai',
            model: $request->model,
            transport: $request->transport,
            connectionUrl: $connectionUrl,
            sessionConfig: $this->buildSessionBody($request),
        );
    }

    public function connect(RealtimeSession $session): WebSocketConnection
    {
        $client = new Client($session->connectionUrl, [
            'headers' => [
                'Authorization' => "Bearer {$this->config->apiKey}",
            ],
        ]);

        return new WebSocketConnection($client, $session->sessionId);
    }
}
