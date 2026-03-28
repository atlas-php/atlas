<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\VoiceTransport;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\ElevenLabs\Handlers\Voice;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\WebSocketConnection;
use Atlasphp\Atlas\Requests\VoiceRequest;
use Atlasphp\Atlas\Responses\VoiceSession;

function makeElevenLabsVoiceHandler(?HttpClient $http = null): Voice
{
    $config = new ProviderConfig(apiKey: 'xi-test-key', baseUrl: 'https://api.elevenlabs.io/v1');

    return new Voice($config, $http ?? Mockery::mock(HttpClient::class));
}

function makeElevenLabsVoiceHandlerWithMocks(
    string $signedUrl = 'wss://api.elevenlabs.io/v1/convai/conversation?signed_url=abc123',
): array {
    $http = Mockery::mock(HttpClient::class);

    $http->shouldReceive('get')
        ->withArgs(fn (string $url) => str_contains($url, 'get-signed-url'))
        ->andReturn(['signed_url' => $signedUrl]);

    $handler = makeElevenLabsVoiceHandler($http);

    return [$handler, $http];
}

function makeElevenLabsVoiceHandlerForDynamicAgent(
    string $agentId = 'agent_123',
    string $signedUrl = 'wss://url',
    ?Closure $postMatcher = null,
): array {
    $http = Mockery::mock(HttpClient::class);

    $postExpectation = $http->shouldReceive('post')
        ->withArgs(fn (string $url) => str_contains($url, '/convai/agents/create'));

    if ($postMatcher !== null) {
        $postExpectation->withArgs($postMatcher);
    }

    $postExpectation->once()->andReturn(['agent_id' => $agentId]);

    $http->shouldReceive('get')->andReturn(['signed_url' => $signedUrl]);

    $handler = makeElevenLabsVoiceHandler($http);

    return [$handler, $http];
}

// ─── createSession with pre-configured agent ────────────────────

it('creates session with pre-configured agent_id', function () {
    [$handler, $http] = makeElevenLabsVoiceHandlerWithMocks();

    $session = $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: 'Be helpful',
        voice: null,
        providerOptions: ['agent_id' => 'agent_abc123'],
    ));

    expect($session->provider)->toBe('elevenlabs');
    expect($session->connectionUrl)->toBe('wss://api.elevenlabs.io/v1/convai/conversation?signed_url=abc123');
    expect($session->transport)->toBe(VoiceTransport::WebSocket);
    expect($session->ephemeralToken)->toBeNull();
    expect($session->meta)->toHaveKey('agent_id', 'agent_abc123');
    expect($session->meta)->not->toHaveKey('dynamic_agent');
});

it('does not call agent creation when agent_id is provided', function () {
    $http = Mockery::mock(HttpClient::class);

    // Should NOT receive post (no agent creation)
    $http->shouldNotReceive('post');

    // Should receive get for signed URL
    $http->shouldReceive('get')
        ->once()
        ->andReturn(['signed_url' => 'wss://signed.url']);

    $handler = makeElevenLabsVoiceHandler($http);

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: null,
        voice: null,
        providerOptions: ['agent_id' => 'existing_agent'],
    ));
});

// ─── createSession with dynamic agent ───────────────────────────

it('creates dynamic agent when no agent_id provided', function () {
    [$handler] = makeElevenLabsVoiceHandlerForDynamicAgent('dynamic_agent_xyz');

    $session = $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: 'Be helpful',
        voice: 'JBFqnCBsd6RMkjVDRZzb',
    ));

    expect($session->meta)->toHaveKey('agent_id', 'dynamic_agent_xyz');
    expect($session->meta)->toHaveKey('dynamic_agent', true);
});

it('throws when agent creation returns no agent_id', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->andReturn(['error' => 'something']);

    $handler = makeElevenLabsVoiceHandler($http);

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: 'Test',
        voice: null,
    ));
})->throws(ProviderException::class, 'no agent_id');

it('throws when signed URL response is missing', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->andReturn(['agent_id' => 'agent_123']);
    $http->shouldReceive('get')->andReturn(['error' => 'missing']);

    $handler = makeElevenLabsVoiceHandler($http);

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: 'Test',
        voice: null,
    ));
})->throws(ProviderException::class, 'signed_url');

// ─── Session properties ─────────────────────────────────────────

it('session ID matches rt_el_ pattern', function () {
    [$handler] = makeElevenLabsVoiceHandlerWithMocks();

    $session = $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: null,
        voice: null,
        providerOptions: ['agent_id' => 'agent_abc123'],
    ));

    expect($session->sessionId)->toMatch('/^rt_el_[0-9a-f]{32}$/');
});

it('always uses WebSocket transport', function () {
    [$handler] = makeElevenLabsVoiceHandlerWithMocks();

    $session = $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: null,
        voice: null,
        transport: VoiceTransport::WebRtc, // Even if WebRTC requested
        providerOptions: ['agent_id' => 'agent_abc123'],
    ));

    expect($session->transport)->toBe(VoiceTransport::WebSocket);
});

it('uses LLM from providerOptions as model', function () {
    [$handler] = makeElevenLabsVoiceHandlerWithMocks();

    $session = $handler->createSession(new VoiceRequest(
        model: 'some-default',
        instructions: null,
        voice: null,
        providerOptions: ['agent_id' => 'agent_abc123', 'llm' => 'claude-sonnet-4-20250514'],
    ));

    expect($session->model)->toBe('claude-sonnet-4-20250514');
});

it('falls back to request model when no llm in providerOptions', function () {
    [$handler] = makeElevenLabsVoiceHandlerWithMocks();

    $session = $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: null,
        voice: null,
        providerOptions: ['agent_id' => 'agent_abc123'],
    ));

    expect($session->model)->toBe('gpt-4o');
});

// ─── Session config overrides ───────────────────────────────────

it('includes instructions in session config override', function () {
    [$handler] = makeElevenLabsVoiceHandlerWithMocks();

    $session = $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: 'You are a support agent.',
        voice: null,
        providerOptions: ['agent_id' => 'agent_abc123'],
    ));

    expect($session->sessionConfig['conversation_config_override']['agent']['prompt']['prompt'])
        ->toBe('You are a support agent.');
});

it('includes voice in session config override', function () {
    [$handler] = makeElevenLabsVoiceHandlerWithMocks();

    $session = $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: null,
        voice: 'JBFqnCBsd6RMkjVDRZzb',
        providerOptions: ['agent_id' => 'agent_abc123'],
    ));

    expect($session->sessionConfig['conversation_config_override']['tts']['voice_id'])
        ->toBe('JBFqnCBsd6RMkjVDRZzb');
});

it('includes first_message in session config override', function () {
    [$handler] = makeElevenLabsVoiceHandlerWithMocks();

    $session = $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: null,
        voice: null,
        providerOptions: [
            'agent_id' => 'agent_abc123',
            'first_message' => 'Hello! How can I help?',
        ],
    ));

    expect($session->sessionConfig['conversation_config_override']['agent']['first_message'])
        ->toBe('Hello! How can I help?');
});

// ─── Agent config for dynamic agents ────────────────────────────

it('maps instructions to agent prompt in dynamic agent config', function () {
    [$handler] = makeElevenLabsVoiceHandlerForDynamicAgent(
        postMatcher: fn (string $url, array $headers, array $body) => $body['conversation_config']['agent']['prompt']['prompt'] === 'Be helpful',
    );

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: 'Be helpful',
        voice: null,
    ));
});

it('maps voice to tts.voice_id in dynamic agent config', function () {
    [$handler] = makeElevenLabsVoiceHandlerForDynamicAgent(
        postMatcher: fn (string $url, array $headers, array $body) => $body['conversation_config']['tts']['voice_id'] === 'custom_voice_id',
    );

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: null,
        voice: 'custom_voice_id',
    ));
});

it('uses default voice when none specified in dynamic agent', function () {
    [$handler] = makeElevenLabsVoiceHandlerForDynamicAgent(
        postMatcher: fn (string $url, array $headers, array $body) => $body['conversation_config']['tts']['voice_id'] === '21m00Tcm4TlvDq8ikWAM',
    );

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: null,
        voice: null,
    ));
});

it('maps temperature in dynamic agent config', function () {
    [$handler] = makeElevenLabsVoiceHandlerForDynamicAgent(
        postMatcher: fn (string $url, array $headers, array $body) => $body['conversation_config']['agent']['prompt']['temperature'] === 0.7,
    );

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: null,
        voice: null,
        temperature: 0.7,
    ));
});

it('maps tools to ElevenLabs client format in dynamic agent', function () {
    [$handler] = makeElevenLabsVoiceHandlerForDynamicAgent(
        postMatcher: function (string $url, array $headers, array $body) {
            $tools = $body['conversation_config']['agent']['prompt']['tools'] ?? [];

            return count($tools) === 1
                && $tools[0]['type'] === 'client'
                && $tools[0]['name'] === 'get_weather'
                && $tools[0]['expects_response'] === true;
        },
    );

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: null,
        voice: null,
        tools: [
            ['type' => 'function', 'name' => 'get_weather', 'description' => 'Get weather', 'parameters' => ['type' => 'object']],
        ],
    ));
});

it('uses xi-api-key header for API calls', function () {
    $http = Mockery::mock(HttpClient::class);

    $capturedHeaders = null;

    $http->shouldReceive('get')
        ->withArgs(function (string $url, array $headers) use (&$capturedHeaders) {
            $capturedHeaders = $headers;

            return true;
        })
        ->andReturn(['signed_url' => 'wss://url']);

    $handler = makeElevenLabsVoiceHandler($http);

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: null,
        voice: null,
        providerOptions: ['agent_id' => 'existing'],
    ));

    expect($capturedHeaders)->toHaveKey('xi-api-key', 'xi-test-key');
    expect($capturedHeaders)->not->toHaveKey('Authorization');
});

// ─── connect() ──────────────────────────────────────────────────

it('connect creates WebSocketConnection with signed URL', function () {
    [$handler] = makeElevenLabsVoiceHandlerWithMocks();

    $session = new VoiceSession(
        sessionId: 'rt_el_test123',
        provider: 'elevenlabs',
        model: 'gpt-4o',
        transport: VoiceTransport::WebSocket,
        connectionUrl: 'wss://api.elevenlabs.io/v1/convai/conversation?signed_url=abc',
    );

    $connection = $handler->connect($session);

    expect($connection)->toBeInstanceOf(WebSocketConnection::class);
    expect($connection->sessionId)->toBe('rt_el_test123');
});

// ─── LLM in dynamic agent ───────────────────────────────────────

it('passes LLM from providerOptions to agent config', function () {
    [$handler] = makeElevenLabsVoiceHandlerForDynamicAgent(
        postMatcher: fn (string $url, array $headers, array $body) => $body['conversation_config']['agent']['prompt']['llm'] === 'claude-sonnet-4-20250514',
    );

    $handler->createSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: null,
        voice: null,
        providerOptions: ['llm' => 'claude-sonnet-4-20250514'],
    ));
});
