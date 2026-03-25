<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Agents\AgentRegistry;
use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Exceptions\AgentNotFoundException;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\VoiceSession;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Support\VariableRegistry;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\StreamResponseFake;
use Atlasphp\Atlas\Testing\StructuredResponseFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Atlasphp\Atlas\Testing\VoiceSessionFake;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

// ─── Test agents ────────────────────────────────────────────────────────────

class RequestTestMinimalAgent extends Agent
{
    public function key(): string
    {
        return 'minimal';
    }
}

class RequestTestConfiguredAgent extends Agent
{
    public function key(): string
    {
        return 'configured';
    }

    public function provider(): Provider|string|null
    {
        return Provider::Anthropic;
    }

    public function model(): ?string
    {
        return 'claude-sonnet-4-20250514';
    }

    public function instructions(): ?string
    {
        return 'You are a helpful assistant for {COMPANY_NAME}.';
    }

    public function temperature(): ?float
    {
        return 0.5;
    }

    public function maxTokens(): ?int
    {
        return 2048;
    }

    public function maxSteps(): ?int
    {
        return 5;
    }

    public function concurrent(): bool
    {
        return false;
    }

    public function providerOptions(): array
    {
        return ['top_k' => 40];
    }
}

class RequestTestToolAgent extends Agent
{
    public function key(): string
    {
        return 'tool-agent';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }

    public function tools(): array
    {
        return [RequestTestEchoTool::class];
    }

    public function providerTools(): array
    {
        return [new WebSearch];
    }
}

class RequestTestEchoTool extends Tool
{
    public function name(): string
    {
        return 'echo';
    }

    public function description(): string
    {
        return 'Echoes input.';
    }

    public function handle(array $args, array $context): mixed
    {
        return $args['text'] ?? 'echo';
    }
}

// ─── Helper ─────────────────────────────────────────────────────────────────

function makeAgentRequest(string $key): AgentRequest
{
    return new AgentRequest(
        key: $key,
        agentRegistry: app(AgentRegistry::class),
        providerRegistry: app(ProviderRegistryContract::class),
        app: app(),
        events: app(Dispatcher::class),
    );
}

function registerTestAgent(string $agentClass): void
{
    app(AgentRegistry::class)->register($agentClass);
}

// ─── Fluent chain ───────────────────────────────────────────────────────────

it('returns self from all fluent methods', function () {
    registerTestAgent(RequestTestMinimalAgent::class);
    $request = makeAgentRequest('minimal');

    expect($request->instructions('hello'))->toBe($request);
    expect($request->message('hello'))->toBe($request);
    expect($request->withMessages([]))->toBe($request);
    expect($request->withVariables([]))->toBe($request);
    expect($request->withMeta([]))->toBe($request);
    expect($request->withTools([]))->toBe($request);
    expect($request->withProviderTools([]))->toBe($request);
    expect($request->withProvider(Provider::OpenAI, 'gpt-4o'))->toBe($request);
    expect($request->withMaxTokens(100))->toBe($request);
    expect($request->withTemperature(0.5))->toBe($request);
    expect($request->withMaxSteps(10))->toBe($request);
    expect($request->withSchema(new Schema('test', 'test', [])))->toBe($request);
    expect($request->withProviderOptions([]))->toBe($request);
    expect($request->withConcurrent(false))->toBe($request);
    expect($request->forConversation(1))->toBe($request);
    expect($request->withMessageLimit(10))->toBe($request);
    expect($request->respond())->toBe($request);
    expect($request->retry())->toBe($request);
    expect($request->queue())->toBe($request);
    expect($request->onConnection('redis'))->toBe($request);
    expect($request->onQueue('high'))->toBe($request);
});

it('withMaxSteps accepts null to disable step limit', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    $request = makeAgentRequest('minimal')->withMaxSteps(null);

    $ref = new ReflectionProperty($request, 'maxStepsOverride');
    expect($ref->getValue($request))->toBeNull();
});

// ─── asText() — no tools ───────────────────────────────────────────────────

it('executes asText without tools via direct driver call', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Hello!'),
    ]);

    app()->instance(AtlasFake::class, $fake);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $response = makeAgentRequest('minimal')
        ->message('Hi')
        ->asText();

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('Hello!');
});

// ─── asText() — with configured agent ──────────────────────────────────────

it('resolves provider and model from agent config', function () {
    registerTestAgent(RequestTestConfiguredAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('OK'),
    ]);

    $response = makeAgentRequest('configured')
        ->message('Hello')
        ->asText();

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('OK');

    $recorded = $fake->recorded();
    expect($recorded)->toHaveCount(1);
    expect($recorded[0]->model)->toBe('claude-sonnet-4-20250514');
});

// ─── withProvider() override ────────────────────────────────────────────────

it('overrides agent provider and model with withProvider', function () {
    registerTestAgent(RequestTestConfiguredAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Override'),
    ]);

    $response = makeAgentRequest('configured')
        ->withProvider(Provider::OpenAI, 'gpt-4o')
        ->message('Hello')
        ->asText();

    expect($response->text)->toBe('Override');

    $recorded = $fake->recorded();
    expect($recorded)->toHaveCount(1);
    expect($recorded[0]->model)->toBe('gpt-4o');
});

// ─── Variable interpolation ────────────────────────────────────────────────

it('interpolates variables into agent instructions', function () {
    registerTestAgent(RequestTestConfiguredAgent::class);

    /** @var VariableRegistry $varRegistry */
    $varRegistry = app(VariableRegistry::class);
    $varRegistry->register('COMPANY_NAME', 'Acme Corp');

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Hi'),
    ]);

    makeAgentRequest('configured')
        ->message('Hello')
        ->asText();

    $recorded = $fake->recorded();
    expect($recorded[0]->request->instructions)->toBe('You are a helpful assistant for Acme Corp.');
});

it('runtime variables override global variables', function () {
    registerTestAgent(RequestTestConfiguredAgent::class);

    /** @var VariableRegistry $varRegistry */
    $varRegistry = app(VariableRegistry::class);
    $varRegistry->register('COMPANY_NAME', 'Acme Corp');

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Hi'),
    ]);

    makeAgentRequest('configured')
        ->withVariables(['COMPANY_NAME' => 'Override Inc'])
        ->message('Hello')
        ->asText();

    $recorded = $fake->recorded();
    expect($recorded[0]->request->instructions)->toBe('You are a helpful assistant for Override Inc.');
});

it('handles null instructions without interpolation', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Hi'),
    ]);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    makeAgentRequest('minimal')
        ->message('Hello')
        ->asText();

    $recorded = $fake->recorded();
    expect($recorded[0]->request->instructions)->toBeNull();
});

// ─── instructions() override ────────────────────────────────────────────────

it('overrides agent instructions', function () {
    registerTestAgent(RequestTestConfiguredAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Hi'),
    ]);

    makeAgentRequest('configured')
        ->instructions('Custom instructions')
        ->message('Hello')
        ->asText();

    $recorded = $fake->recorded();
    expect($recorded[0]->request->instructions)->toBe('Custom instructions');
});

// ─── Config overrides ──────────────────────────────────────────────────────

it('passes agent temperature and maxTokens to request', function () {
    registerTestAgent(RequestTestConfiguredAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Hi'),
    ]);

    makeAgentRequest('configured')
        ->message('Hello')
        ->asText();

    $recorded = $fake->recorded();
    expect($recorded[0]->request->temperature)->toBe(0.5);
    expect($recorded[0]->request->maxTokens)->toBe(2048);
});

it('runtime overrides take precedence over agent config', function () {
    registerTestAgent(RequestTestConfiguredAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Hi'),
    ]);

    makeAgentRequest('configured')
        ->withTemperature(0.9)
        ->withMaxTokens(1000)
        ->message('Hello')
        ->asText();

    $recorded = $fake->recorded();
    expect($recorded[0]->request->temperature)->toBe(0.9);
    expect($recorded[0]->request->maxTokens)->toBe(1000);
});

it('passes provider options from agent', function () {
    registerTestAgent(RequestTestConfiguredAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Hi'),
    ]);

    makeAgentRequest('configured')
        ->message('Hello')
        ->asText();

    $recorded = $fake->recorded();
    expect($recorded[0]->request->providerOptions)->toBe(['top_k' => 40]);
});

it('withProviderOptions overrides agent options', function () {
    registerTestAgent(RequestTestConfiguredAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Hi'),
    ]);

    makeAgentRequest('configured')
        ->withProviderOptions(['top_p' => 0.8])
        ->message('Hello')
        ->asText();

    $recorded = $fake->recorded();
    expect($recorded[0]->request->providerOptions)->toBe(['top_p' => 0.8]);
});

// ─── Provider tools ─────────────────────────────────────────────────────────

it('merges agent and runtime provider tools', function () {
    registerTestAgent(RequestTestToolAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Hi'),
    ]);

    makeAgentRequest('tool-agent')
        ->withProviderTools([new WebSearch])
        ->message('Hello')
        ->asText();

    $recorded = $fake->recorded();
    // Agent has 1 WebSearch, runtime adds another
    expect($recorded[0]->request->providerTools)->toHaveCount(2);
});

// ─── Meta passthrough ───────────────────────────────────────────────────────

it('passes meta through to the request', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Hi'),
    ]);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    makeAgentRequest('minimal')
        ->withMeta(['user_id' => 42])
        ->message('Hello')
        ->asText();

    $recorded = $fake->recorded();
    expect($recorded[0]->request->meta)->toBe(['user_id' => 42]);
});

it('withMeta merges multiple calls', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Hi'),
    ]);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    makeAgentRequest('minimal')
        ->withMeta(['user_id' => 42])
        ->withMeta(['session_id' => 'abc'])
        ->message('Hello')
        ->asText();

    $recorded = $fake->recorded();
    expect($recorded[0]->request->meta)->toBe(['user_id' => 42, 'session_id' => 'abc']);
});

it('withVariables merges multiple calls recursively', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $request = makeAgentRequest('minimal')
        ->withVariables(['user' => ['name' => 'Tim']])
        ->withVariables(['user' => ['role' => 'admin']]);

    // Use reflection to check merged state
    $ref = new ReflectionProperty($request, 'variables');
    $variables = $ref->getValue($request);

    expect($variables)->toBe(['user' => ['name' => 'Tim', 'role' => 'admin']]);
});

// ─── asStream() ─────────────────────────────────────────────────────────────

it('executes asStream without tools', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    // Use a real fake that supports streaming
    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Streamed'),
    ]);

    $response = makeAgentRequest('minimal')
        ->message('Hi')
        ->asStream();

    expect($response)->toBeInstanceOf(StreamResponse::class);
});

// ─── asStructured() ─────────────────────────────────────────────────────────

it('executes asStructured', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        StructuredResponseFake::make()->withStructured(['name' => 'test']),
    ]);

    $response = makeAgentRequest('minimal')
        ->withSchema(new Schema('Test', 'Test schema', [
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
        ]))
        ->message('Give me data')
        ->asStructured();

    expect($response)->toBeInstanceOf(StructuredResponse::class);
    expect($response->structured)->toBe(['name' => 'test']);
});

// ─── Agent resolution ───────────────────────────────────────────────────────

it('throws when agent key is not registered', function () {
    makeAgentRequest('nonexistent')
        ->message('Hello')
        ->asText();
})->throws(AgentNotFoundException::class, 'Agent [nonexistent] is not registered');

// ─── Provider resolution fallback ──────────────────────────────────────────

it('throws when no provider is configured anywhere', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => null, 'model' => null]]);

    makeAgentRequest('minimal')
        ->message('Hello')
        ->asText();
})->throws(AtlasException::class);

// ─── Tool execution path ────────────────────────────────────────────────────

it('executes asText through executor when agent has tools', function () {
    registerTestAgent(RequestTestToolAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Tool response'),
    ]);

    $response = makeAgentRequest('tool-agent')
        ->message('Hello')
        ->asText();

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('Tool response');
    // Steps in meta verify the executor path was used
    expect($response->meta)->toHaveKey('conversation_id');
    expect($response->meta)->toHaveKey('execution_id');
});

it('merges agent tools with runtime tools via withTools', function () {
    registerTestAgent(RequestTestToolAgent::class);

    $extraTool = new class extends Tool
    {
        public function name(): string
        {
            return 'extra';
        }

        public function description(): string
        {
            return 'Extra tool.';
        }

        public function handle(array $args, array $context): mixed
        {
            return 'extra';
        }
    };

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('OK'),
    ]);

    $response = makeAgentRequest('tool-agent')
        ->withTools([$extraTool])
        ->message('Hello')
        ->asText();

    expect($response->text)->toBe('OK');

    // Verify both tools were included in the request
    $recorded = $fake->recorded();
    expect($recorded[0]->request->tools)->toHaveCount(2);
});

it('resolves Tool instances passed directly (not just class strings)', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $toolInstance = new RequestTestEchoTool;

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('OK'),
    ]);

    $response = makeAgentRequest('minimal')
        ->withTools([$toolInstance])
        ->message('Hello')
        ->asText();

    expect($response->text)->toBe('OK');

    $recorded = $fake->recorded();
    expect($recorded[0]->request->tools)->toHaveCount(1);
    expect($recorded[0]->request->tools[0]->name)->toBe('echo');
});

// ─── Invalid tool class ──────────────────────────────────────────────────────

it('throws when withTools receives a non-existent class string', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('OK'),
    ]);

    makeAgentRequest('minimal')
        ->withTools(['App\\NonExistent\\FakeTool'])
        ->message('Hello')
        ->asText();
})->throws(AtlasException::class, 'Tool class [App\\NonExistent\\FakeTool] does not exist');

// ─── Streaming with tools ───────────────────────────────────────────────────

it('asStream with tools falls back to non-streaming and wraps as chunks', function () {
    registerTestAgent(RequestTestToolAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Streamed with tools'),
    ]);

    $response = makeAgentRequest('tool-agent')
        ->message('Hello')
        ->asStream();

    expect($response)->toBeInstanceOf(StreamResponse::class);

    // Iterate to consume the stream
    $chunks = [];
    foreach ($response as $chunk) {
        $chunks[] = $chunk;
    }

    // resultToChunks yields word-level Text chunks then a Done chunk
    $textChunks = array_filter($chunks, fn ($c) => $c->type === ChunkType::Text);
    $doneChunks = array_filter($chunks, fn ($c) => $c->type === ChunkType::Done);

    expect($textChunks)->not->toBeEmpty();
    expect($doneChunks)->toHaveCount(1);

    // Reassembled text matches the original
    $reassembled = implode('', array_map(fn ($c) => $c->text, $textChunks));
    expect($reassembled)->toBe('Streamed with tools');
});

// ─── Structured output ignores tools ────────────────────────────────────────

it('asStructured does not pass agent tools to the request', function () {
    registerTestAgent(RequestTestToolAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        StructuredResponseFake::make()->withStructured(['result' => 'ok']),
    ]);

    $response = makeAgentRequest('tool-agent')
        ->withSchema(new Schema('Test', 'Test', ['type' => 'object']))
        ->message('Hello')
        ->asStructured();

    $recorded = $fake->recorded();
    expect($recorded[0]->request->tools)->toBe([]);
});

// ─── withMessages ───────────────────────────────────────────────────────────

it('passes withMessages to the request', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('OK'),
    ]);

    makeAgentRequest('minimal')
        ->withMessages([
            ['role' => 'user', 'content' => 'First message'],
            ['role' => 'assistant', 'content' => 'First reply'],
        ])
        ->message('Hello')
        ->asText();

    $recorded = $fake->recorded();
    expect($recorded[0]->request->messages)->toHaveCount(2);
});

// ─── Queue support ──────────────────────────────────────────────────────────

it('queued asText returns PendingExecution', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    Queue::fake();

    $result = makeAgentRequest('minimal')
        ->message('Hello')
        ->queue()
        ->asText();

    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('queued asStream returns PendingExecution', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    Queue::fake();

    $result = makeAgentRequest('minimal')
        ->message('Hello')
        ->queue()
        ->asStream();

    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('queued asStructured returns PendingExecution', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    Queue::fake();

    $result = makeAgentRequest('minimal')
        ->withSchema(new Schema('Test', 'Test', ['type' => 'object']))
        ->message('Hello')
        ->queue()
        ->asStructured();

    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('toQueuePayload serializes agent key and message', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $request = makeAgentRequest('minimal')
        ->message('Hello world');

    $payload = $request->toQueuePayload();

    expect($payload['key'])->toBe('minimal')
        ->and($payload['message'])->toBe('Hello world')
        ->and($payload['instructions'])->toBeNull()
        ->and($payload['variables'])->toBe([])
        ->and($payload['meta'])->toBe([])
        ->and($payload['provider'])->toBeNull()
        ->and($payload['model'])->toBeNull()
        ->and($payload['conversation_id'])->toBeNull()
        ->and($payload['respond_mode'])->toBeFalse()
        ->and($payload['retry_mode'])->toBeFalse();
});

it('toQueuePayload serializes all overrides', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $request = makeAgentRequest('minimal')
        ->message('Hello')
        ->instructions('Custom instructions')
        ->withVariables(['FOO' => 'bar'])
        ->withMeta(['user_id' => 42])
        ->withProvider(Provider::Anthropic, 'claude-sonnet-4-20250514')
        ->withMaxTokens(500)
        ->withTemperature(0.9)
        ->withMaxSteps(5)
        ->withConcurrent(false)
        ->withProviderOptions(['top_k' => 10])
        ->forConversation(99)
        ->withMessageLimit(20);

    $payload = $request->toQueuePayload();

    expect($payload['key'])->toBe('minimal')
        ->and($payload['message'])->toBe('Hello')
        ->and($payload['instructions'])->toBe('Custom instructions')
        ->and($payload['variables'])->toBe(['FOO' => 'bar'])
        ->and($payload['meta'])->toBe(['user_id' => 42])
        ->and($payload['provider'])->toBe('anthropic')
        ->and($payload['model'])->toBe('claude-sonnet-4-20250514')
        ->and($payload['max_tokens'])->toBe(500)
        ->and($payload['temperature'])->toBe(0.9)
        ->and($payload['max_steps'])->toBe(5)
        ->and($payload['concurrent'])->toBeFalse()
        ->and($payload['provider_options'])->toBe(['top_k' => 10])
        ->and($payload['conversation_id'])->toBe(99)
        ->and($payload['message_limit'])->toBe(20);
});

it('toQueuePayload serializes respond and retry modes', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $request = makeAgentRequest('minimal')
        ->forConversation(1)
        ->respond();

    $payload = $request->toQueuePayload();

    expect($payload['respond_mode'])->toBeTrue()
        ->and($payload['retry_mode'])->toBeFalse();

    $retryRequest = makeAgentRequest('minimal')
        ->forConversation(1)
        ->retry();

    $retryPayload = $retryRequest->toQueuePayload();

    expect($retryPayload['retry_mode'])->toBeTrue()
        ->and($retryPayload['respond_mode'])->toBeFalse();
});

it('executeFromPayload rebuilds and executes asText', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('Rebuilt'),
    ]);

    $result = AgentRequest::executeFromPayload(
        payload: [
            'key' => 'minimal',
            'message' => 'Hello',
            'instructions' => null,
            'variables' => [],
            'meta' => [],
            'provider' => null,
            'model' => null,
            'max_tokens' => null,
            'temperature' => null,
            'max_steps' => null,
            'concurrent' => null,
            'provider_options' => [],
            'conversation_id' => null,
            'owner_type' => null,
            'owner_id' => null,
            'message_owner_type' => null,
            'message_owner_id' => null,
            'message_limit' => null,
            'respond_mode' => false,
            'retry_mode' => false,
        ],
        terminal: 'asText',
    );

    expect($result)->toBeInstanceOf(TextResponse::class);
    expect($result->text)->toBe('Rebuilt');
});

it('executeFromPayload applies overrides', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('OK'),
    ]);

    $result = AgentRequest::executeFromPayload(
        payload: [
            'key' => 'minimal',
            'message' => 'Hello',
            'instructions' => 'Be brief',
            'variables' => ['APP' => 'Test'],
            'meta' => ['user_id' => 1],
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'max_tokens' => 100,
            'temperature' => 0.5,
            'max_steps' => 3,
            'concurrent' => false,
            'provider_options' => ['top_p' => 0.9],
            'conversation_id' => null,
            'owner_type' => null,
            'owner_id' => null,
            'message_owner_type' => null,
            'message_owner_id' => null,
            'message_limit' => 10,
            'respond_mode' => false,
            'retry_mode' => false,
        ],
        terminal: 'asText',
    );

    expect($result)->toBeInstanceOf(TextResponse::class);

    $recorded = $fake->recorded();
    expect($recorded[0]->request->model)->toBe('gpt-4o');
    expect($recorded[0]->request->maxTokens)->toBe(100);
    expect($recorded[0]->request->temperature)->toBe(0.5);
    expect($recorded[0]->request->providerOptions)->toBe(['top_p' => 0.9]);
});

it('executeFromPayload throws on unknown terminal', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('OK'),
    ]);

    AgentRequest::executeFromPayload(
        payload: [
            'key' => 'minimal',
            'message' => 'Hello',
            'instructions' => null,
            'variables' => [],
            'meta' => [],
            'provider' => null,
            'model' => null,
            'max_tokens' => null,
            'temperature' => null,
            'max_steps' => null,
            'concurrent' => null,
            'provider_options' => [],
            'conversation_id' => null,
            'owner_type' => null,
            'owner_id' => null,
            'message_owner_type' => null,
            'message_owner_id' => null,
            'message_limit' => null,
            'respond_mode' => false,
            'retry_mode' => false,
        ],
        terminal: 'asUnknown',
    );
})->throws(InvalidArgumentException::class, 'Unknown terminal method: asUnknown');

it('resolveProviderKey falls back through agent then config', function () {
    registerTestAgent(RequestTestMinimalAgent::class);
    registerTestAgent(RequestTestConfiguredAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    // Minimal agent — no provider override, no agent provider → falls to config
    $method = new ReflectionMethod(AgentRequest::class, 'resolveProviderKey');
    $minimalRequest = makeAgentRequest('minimal');
    expect($method->invoke($minimalRequest))->toBe('openai');

    // Configured agent — has agent provider
    $configuredRequest = makeAgentRequest('configured');
    expect($method->invoke($configuredRequest))->toBe('anthropic');

    // Explicit override wins
    $overrideRequest = makeAgentRequest('minimal')
        ->withProvider(Provider::xAI, 'grok');
    expect($method->invoke($overrideRequest))->toBe('xai');
});

it('resolveModelKey falls back through agent then config', function () {
    registerTestAgent(RequestTestMinimalAgent::class);
    registerTestAgent(RequestTestConfiguredAgent::class);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $method = new ReflectionMethod(AgentRequest::class, 'resolveModelKey');

    // Minimal agent → falls to config
    $minimalRequest = makeAgentRequest('minimal');
    expect($method->invoke($minimalRequest))->toBe('gpt-4o-mini');

    // Configured agent
    $configuredRequest = makeAgentRequest('configured');
    expect($method->invoke($configuredRequest))->toBe('claude-sonnet-4-20250514');

    // Explicit override
    $overrideRequest = makeAgentRequest('minimal')
        ->withProvider('openai', 'gpt-5');
    expect($method->invoke($overrideRequest))->toBe('gpt-5');
});

// ─── Conversation support ───────────────────────────────────────────

it('for() sets conversation owner and returns self', function () {
    $owner = new class extends Model
    {
        protected $table = 'users';
    };

    $request = makeAgentRequest('minimal');
    $result = $request->for($owner);

    expect($result)->toBe($request);
});

it('asUser() sets message owner and returns self', function () {
    $author = new class extends Model
    {
        protected $table = 'users';
    };

    $request = makeAgentRequest('minimal');
    $result = $request->asUser($author);

    expect($result)->toBe($request);
});

// ─── Modality events on error ───────────────────────────────────────

it('fires ModalityStarted and ModalityCompleted on asText', function () {
    Event::fake();
    Atlas::fake([TextResponseFake::make()->withText('ok')]);

    registerTestAgent(RequestTestConfiguredAgent::class);
    Atlas::agent('configured')->message('hello')->asText();

    Event::assertDispatched(
        ModalityStarted::class,
        fn ($e) => $e->modality === Modality::Text
    );
    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Text
    );
});

// ─── restoreMediaItem ───────────────────────────────────────────────

it('restoreMediaItem restores base64 image', function () {
    $method = new ReflectionMethod(AgentRequest::class, 'restoreMediaItem');

    $result = $method->invoke(null, [
        'class' => Image::class,
        'mime' => 'image/png',
        'base64' => 'iVBORw0KGgo=',
        'url' => null,
        'storage_path' => null,
        'storage_disk' => null,
        'path' => null,
        'file_id' => null,
    ]);

    expect($result)->toBeInstanceOf(Image::class)
        ->and($result->isBase64())->toBeTrue()
        ->and($result->mimeType())->toBe('image/png');
});

it('restoreMediaItem returns null for invalid class', function () {
    $method = new ReflectionMethod(AgentRequest::class, 'restoreMediaItem');

    $result = $method->invoke(null, [
        'class' => 'NonExistentClass',
        'mime' => 'image/png',
        'base64' => null,
        'url' => null,
        'storage_path' => null,
        'storage_disk' => null,
        'path' => null,
        'file_id' => null,
    ]);

    expect($result)->toBeNull();
});

it('restoreMediaItem restores url image', function () {
    $method = new ReflectionMethod(AgentRequest::class, 'restoreMediaItem');

    $result = $method->invoke(null, [
        'class' => Image::class,
        'mime' => 'image/jpeg',
        'base64' => null,
        'url' => 'https://example.com/photo.jpg',
        'storage_path' => null,
        'storage_disk' => null,
        'path' => null,
        'file_id' => null,
    ]);

    expect($result)->toBeInstanceOf(Image::class)
        ->and($result->isUrl())->toBeTrue()
        ->and($result->url())->toBe('https://example.com/photo.jpg');
});

it('restoreMediaItem restores storage image', function () {
    $method = new ReflectionMethod(AgentRequest::class, 'restoreMediaItem');

    $result = $method->invoke(null, [
        'class' => Image::class,
        'mime' => 'image/png',
        'base64' => null,
        'url' => null,
        'storage_path' => 'atlas/test.png',
        'storage_disk' => 'local',
        'path' => null,
        'file_id' => null,
    ]);

    expect($result)->toBeInstanceOf(Image::class)
        ->and($result->isStorage())->toBeTrue()
        ->and($result->storagePath())->toBe('atlas/test.png');
});

// ─── Error handling — ModalityCompleted fires on exceptions ─────────────────

it('asText fires ModalityCompleted and re-throws on error', function () {
    registerTestAgent(RequestTestMinimalAgent::class);
    Event::fake([ModalityStarted::class, ModalityCompleted::class]);

    // Register a driver that throws on text()
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));
    $driver->shouldReceive('text')->andThrow(new RuntimeException('Provider down'));

    $registry = app(ProviderRegistryContract::class);
    $registry->register('openai', fn () => $driver);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o']]);

    try {
        makeAgentRequest('minimal')->message('Hi')->asText();
        $this->fail('Expected exception');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Provider down');
    }

    Event::assertDispatched(ModalityCompleted::class, function (ModalityCompleted $event) {
        return $event->modality === Modality::Text;
    });
});

it('asStream fires ModalityCompleted and re-throws on error', function () {
    registerTestAgent(RequestTestMinimalAgent::class);
    Event::fake([ModalityStarted::class, ModalityCompleted::class]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(stream: true));
    $driver->shouldReceive('stream')->andThrow(new RuntimeException('Stream failed'));

    $registry = app(ProviderRegistryContract::class);
    $registry->register('openai', fn () => $driver);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o']]);

    try {
        makeAgentRequest('minimal')->message('Hi')->asStream();
        $this->fail('Expected exception');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Stream failed');
    }

    Event::assertDispatched(ModalityCompleted::class, function (ModalityCompleted $event) {
        return $event->modality === Modality::Stream;
    });
});

it('asStructured fires ModalityCompleted and re-throws on error', function () {
    registerTestAgent(RequestTestMinimalAgent::class);
    Event::fake([ModalityStarted::class, ModalityCompleted::class]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(structured: true));
    $driver->shouldReceive('structured')->andThrow(new RuntimeException('Structured failed'));

    $registry = app(ProviderRegistryContract::class);
    $registry->register('openai', fn () => $driver);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o']]);

    try {
        makeAgentRequest('minimal')->message('Hi')->asStructured();
        $this->fail('Expected exception');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Structured failed');
    }

    Event::assertDispatched(ModalityCompleted::class, function (ModalityCompleted $event) {
        return $event->modality === Modality::Structured;
    });
});

it('asStream pipes broadcast channel to the stream response', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        StreamResponseFake::make()->withText('Hello world'),
    ]);

    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o']]);

    $channel = new PrivateChannel('test-channel');

    $result = makeAgentRequest('minimal')
        ->message('Hi')
        ->broadcastOn($channel)
        ->asStream();

    expect($result)->toBeInstanceOf(StreamResponse::class);
});

it('asVoice returns a VoiceSession with the correct provider', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        VoiceSessionFake::make()->withProvider('openai'),
    ]);

    config(['atlas.defaults.voice' => ['provider' => 'openai', 'model' => 'gpt-4o-realtime-preview']]);

    $session = makeAgentRequest('minimal')->asVoice();

    expect($session)->toBeInstanceOf(VoiceSession::class);
    expect($session->provider)->toBe('openai');
});

// ─── dispatchAgentMiddleware ────────────────────────────────────────────────

it('dispatches through agent middleware when configured', function () {
    registerTestAgent(RequestTestMinimalAgent::class);
    $middlewareRan = false;

    app()->bind('test-agent-middleware', function () use (&$middlewareRan) {
        return new class($middlewareRan)
        {
            public function __construct(private bool &$ran) {}

            public function handle(AgentContext $context, Closure $next): mixed
            {
                $this->ran = true;

                return $next($context);
            }
        };
    });

    config(['atlas.middleware.agent' => ['test-agent-middleware']]);
    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('middleware ran'),
    ]);
    app()->instance(AtlasFake::class, $fake);

    $response = makeAgentRequest('minimal')->message('test')->asText();

    expect($middlewareRan)->toBeTrue();
    expect($response->text)->toBe('middleware ran');
});

it('skips middleware stack when no agent middleware configured', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    config(['atlas.middleware.agent' => []]);
    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('no middleware'),
    ]);
    app()->instance(AtlasFake::class, $fake);

    $response = makeAgentRequest('minimal')->message('test')->asText();

    expect($response->text)->toBe('no middleware');
});

it('agent middleware can modify context meta', function () {
    registerTestAgent(RequestTestMinimalAgent::class);

    app()->bind('test-mutate-middleware', function () {
        return new class
        {
            public function handle(AgentContext $context, Closure $next): mixed
            {
                $context->meta['injected'] = true;

                return $next($context);
            }
        };
    });

    config(['atlas.middleware.agent' => ['test-mutate-middleware']]);
    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);

    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('mutated'),
    ]);
    app()->instance(AtlasFake::class, $fake);

    $response = makeAgentRequest('minimal')->message('test')->asText();

    expect($response->text)->toBe('mutated');
});
