<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Agents\AgentRegistry;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Responses\VoiceSession;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\VoiceSessionFake;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;

// ─── Test agents ────────────────────────────────────────────────────────────

class VoiceTestNoProviderAgent extends Agent
{
    public function key(): string
    {
        return 'voice-no-provider';
    }
}

class VoiceTestConfiguredAgent extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'voice-configured';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o-realtime-preview';
    }

    public function instructions(): ?string
    {
        return 'You are a helpful voice assistant for {COMPANY_NAME}.';
    }

    public function voice(): ?string
    {
        return 'nova';
    }

    public function temperature(): ?float
    {
        return 0.7;
    }

    public function tools(): array
    {
        return [VoiceTestEchoToolForRequest::class];
    }
}

class VoiceTestEchoToolForRequest extends Tool
{
    public function name(): string
    {
        return 'echo';
    }

    public function description(): string
    {
        return 'Echoes input.';
    }

    public function parameters(): array
    {
        return [
            Schema::string('text', 'Text to echo'),
        ];
    }

    public function handle(array $args, array $context): string
    {
        return $args['text'] ?? 'empty';
    }
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeVoiceAgentRequest(string $key): AgentRequest
{
    return new AgentRequest(
        key: $key,
        agentRegistry: app(AgentRegistry::class),
        providerRegistry: app(ProviderRegistryContract::class),
        app: app(),
        events: app(Dispatcher::class),
    );
}

function registerVoiceTestAgent(string $agentClass): void
{
    app(AgentRegistry::class)->register($agentClass);
}

function createVoiceFake(string $sessionId = 'fake-session-1'): AtlasFake
{
    return new AtlasFake(app(ProviderRegistryContract::class), [
        VoiceSessionFake::make()
            ->withSessionId($sessionId)
            ->withProvider('openai')
            ->withModel('gpt-4o-realtime-preview'),
    ]);
}

// ─── Missing provider ──────────────────────────────────────────────────────

it('throws AtlasException when no voice provider is configured', function () {
    registerVoiceTestAgent(VoiceTestNoProviderAgent::class);

    config(['atlas.defaults.voice' => ['provider' => null, 'model' => null]]);

    expect(fn () => makeVoiceAgentRequest('voice-no-provider')->asVoice())
        ->toThrow(AtlasException::class);
});

// ─── Instructions interpolation ────────────────────────────────────────────

it('interpolates variables in voice instructions', function () {
    registerVoiceTestAgent(VoiceTestConfiguredAgent::class);
    $fake = createVoiceFake();

    $session = makeVoiceAgentRequest('voice-configured')
        ->withVariables(['COMPANY_NAME' => 'Acme Corp'])
        ->asVoice();

    expect($session)->toBeInstanceOf(VoiceSession::class);

    $recorded = $fake->recorded();
    expect($recorded)->toHaveCount(1);
    expect($recorded[0]->request->instructions)->toContain('Acme Corp');
});

// ─── Voice and temperature ─────────────────────────────────────────────────

it('sets voice from agent config', function () {
    registerVoiceTestAgent(VoiceTestConfiguredAgent::class);
    $fake = createVoiceFake();

    makeVoiceAgentRequest('voice-configured')->asVoice();

    $recorded = $fake->recorded();
    expect($recorded)->toHaveCount(1);
    expect($recorded[0]->request->voice)->toBe('nova');
});

it('sets temperature from agent config', function () {
    registerVoiceTestAgent(VoiceTestConfiguredAgent::class);
    $fake = createVoiceFake();

    makeVoiceAgentRequest('voice-configured')->asVoice();

    $recorded = $fake->recorded();
    expect($recorded)->toHaveCount(1);
    expect($recorded[0]->request->temperature)->toBe(0.7);
});

// ─── Tools registration ────────────────────────────────────────────────────

it('registers tool definitions from agent tools', function () {
    registerVoiceTestAgent(VoiceTestConfiguredAgent::class);
    $fake = createVoiceFake();

    makeVoiceAgentRequest('voice-configured')->asVoice();

    $recorded = $fake->recorded();
    expect($recorded)->toHaveCount(1);
    expect($recorded[0]->request->tools)->not->toBeEmpty();

    $toolDef = $recorded[0]->request->tools[0];
    expect($toolDef['name'])->toBe('echo');
    expect($toolDef['type'])->toBe('function');
    expect($toolDef['description'])->toBe('Echoes input.');
    expect($toolDef)->toHaveKey('parameters');
});

// ─── Cache storage ─────────────────────────────────────────────────────────

it('caches tool map when agent has tools', function () {
    registerVoiceTestAgent(VoiceTestConfiguredAgent::class);
    createVoiceFake('cache-test-session');

    $session = makeVoiceAgentRequest('voice-configured')->asVoice();

    $cached = Cache::get("voice:{$session->sessionId}:tools");
    expect($cached)->not->toBeNull();
    expect($cached['tools'])->toHaveKey('echo');
    expect($cached['tools']['echo'])->toBe(VoiceTestEchoToolForRequest::class);
});

// ─── Session returned ──────────────────────────────────────────────────────

it('returns VoiceSession with endpoints', function () {
    registerVoiceTestAgent(VoiceTestConfiguredAgent::class);
    createVoiceFake('endpoint-session');

    $session = makeVoiceAgentRequest('voice-configured')->asVoice();

    expect($session)->toBeInstanceOf(VoiceSession::class);
    expect($session->toolEndpoint)->not->toBeNull();
    expect($session->transcriptEndpoint)->not->toBeNull();
    expect($session->closeEndpoint)->not->toBeNull();
});
