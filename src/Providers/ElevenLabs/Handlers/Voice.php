<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ElevenLabs\Handlers;

use Atlasphp\Atlas\Enums\VoiceTransport;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Providers\ElevenLabs\Concerns\BuildsElevenLabsHeaders;
use Atlasphp\Atlas\Providers\Handlers\VoiceHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\WebSocketConnection;
use Atlasphp\Atlas\Requests\VoiceRequest;
use Atlasphp\Atlas\Responses\VoiceSession;
use WebSocket\Client;

/**
 * ElevenLabs Conversational AI voice handler.
 *
 * Unlike OpenAI/xAI which use ephemeral tokens for direct WebSocket/WebRTC
 * connections, ElevenLabs uses an agent-based model:
 *
 *   1. An agent must exist (created via API or pre-configured on dashboard)
 *   2. A signed URL is generated for client-side WebSocket connection
 *   3. The client connects and can override config per-session
 *
 * ElevenLabs is a pipeline (STT → LLM → TTS), not native speech-to-speech.
 * The consumer chooses which LLM powers reasoning and which voice speaks.
 *
 * Tool execution uses a different protocol than OpenAI/xAI:
 *   - Client tools: `client_tool_call` → execute → `client_tool_result`
 *   - Server tools: ElevenLabs calls your webhook URL directly (configured on agent)
 */
class Voice implements VoiceHandler
{
    use BuildsElevenLabsHeaders;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function createSession(VoiceRequest $request): VoiceSession
    {
        $agentId = $request->providerOptions['agent_id'] ?? null;
        $dynamicAgent = false;

        if ($agentId === null) {
            $agentId = $this->createDynamicAgent($request);
            $dynamicAgent = true;
        }

        $signedUrl = $this->getSignedUrl($agentId);

        return new VoiceSession(
            sessionId: 'rt_el_'.bin2hex(random_bytes(16)),
            provider: 'elevenlabs',
            model: $request->providerOptions['llm'] ?? $request->model,
            transport: VoiceTransport::WebSocket,
            connectionUrl: $signedUrl,
            sessionConfig: $this->buildSessionOverrides($request),
            meta: array_filter([
                'agent_id' => $agentId,
                'dynamic_agent' => $dynamicAgent ?: null,
            ]),
        );
    }

    public function connect(VoiceSession $session): WebSocketConnection
    {
        // Signed URLs embed authentication — no xi-api-key header needed.
        // The URL token is the sole auth mechanism for both browser and server connections.
        $client = new Client($session->connectionUrl);

        return new WebSocketConnection($client, $session->sessionId);
    }

    // ─── Agent Management ────────────────────────────────────────

    /**
     * Create a dynamic agent via the ElevenLabs API.
     *
     * POST /v1/convai/agents/create
     *
     * Dynamic agents persist in the ElevenLabs account. Consumers creating
     * many dynamic agents should implement cleanup via DELETE /v1/convai/agents/{agent_id}
     * or use pre-configured agents via providerOptions['agent_id'].
     */
    protected function createDynamicAgent(VoiceRequest $request): string
    {
        $data = $this->http->post(
            url: "{$this->config->baseUrl}/convai/agents/create",
            headers: $this->headers(),
            body: $this->buildAgentConfig($request),
            timeout: $this->config->timeout,
        );

        $agentId = $data['agent_id'] ?? null;

        if ($agentId === null) {
            throw new ProviderException(
                provider: 'elevenlabs',
                model: $request->model,
                statusCode: 500,
                providerMessage: 'ElevenLabs agent creation failed: no agent_id in response.',
            );
        }

        return $agentId;
    }

    /**
     * Get a signed WebSocket URL for client-side connection.
     *
     * GET /v1/convai/conversation/get-signed-url?agent_id={agent_id}
     */
    protected function getSignedUrl(string $agentId): string
    {
        $data = $this->http->get(
            url: "{$this->config->baseUrl}/convai/conversation/get-signed-url?".http_build_query(['agent_id' => $agentId]),
            headers: $this->headersWithoutContentType(),
            timeout: $this->config->timeout,
        );

        $signedUrl = $data['signed_url'] ?? null;

        if ($signedUrl === null) {
            throw new ProviderException(
                provider: 'elevenlabs',
                model: 'convai',
                statusCode: 500,
                providerMessage: 'ElevenLabs signed URL response missing signed_url.',
            );
        }

        return $signedUrl;
    }

    // ─── Config Building ─────────────────────────────────────────

    /**
     * Build the full agent config for dynamic agent creation.
     *
     * @return array<string, mixed>
     */
    protected function buildAgentConfig(VoiceRequest $request): array
    {
        $prompt = array_filter([
            'prompt' => $request->instructions ?? 'You are a helpful assistant.',
            'llm' => $request->providerOptions['llm'] ?? null,
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxResponseTokens,
        ], fn (mixed $v): bool => $v !== null);

        $tools = $this->mapToolsToClientFormat($request->tools);

        if ($tools !== []) {
            $prompt['tools'] = $tools;
        }

        $agent = array_filter([
            'prompt' => $prompt,
            'first_message' => $request->providerOptions['first_message'] ?? null,
            'language' => $request->providerOptions['language'] ?? null,
        ], fn (mixed $v): bool => $v !== null);

        return [
            'conversation_config' => [
                'agent' => $agent,
                'tts' => [
                    'voice_id' => $request->voice ?? self::DEFAULT_VOICE_ID,
                ],
            ],
            'name' => $request->providerOptions['agent_name'] ?? 'Atlas Voice '.date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Build the conversation_initiation_client_data for per-session overrides.
     *
     * The frontend sends this as the first WebSocket message. Allows overriding
     * the agent's base config without modifying the agent itself.
     *
     * @return array<string, mixed>
     */
    protected function buildSessionOverrides(VoiceRequest $request): array
    {
        $override = [];

        if ($request->instructions !== null) {
            $override['agent']['prompt']['prompt'] = $request->instructions;
        }

        if ($request->voice !== null) {
            $override['tts']['voice_id'] = $request->voice;
        }

        if (isset($request->providerOptions['first_message'])) {
            $override['agent']['first_message'] = $request->providerOptions['first_message'];
        }

        if (isset($request->providerOptions['language'])) {
            $override['agent']['language'] = $request->providerOptions['language'];
        }

        return $override !== [] ? ['conversation_config_override' => $override] : [];
    }

    /**
     * Map tools from OpenAI function format to ElevenLabs client_tool format.
     *
     * Input: [{ type: 'function', name, description, parameters }]
     * Output: [{ type: 'client', name, description, parameters, expects_response: true }]
     *
     * @param  array<int, mixed>  $tools
     * @return array<int, array<string, mixed>>
     */
    protected function mapToolsToClientFormat(array $tools): array
    {
        $mapped = [];

        foreach ($tools as $tool) {
            if (! is_array($tool) || empty($tool['name'])) {
                continue;
            }

            $mapped[] = [
                'type' => 'client',
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
                'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => []],
                'expects_response' => true,
            ];
        }

        return $mapped;
    }
}
