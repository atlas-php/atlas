<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Agents\AgentRegistry;
use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\AgentNotFoundException;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Support\VariableRegistry;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\StructuredResponseFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Contracts\Events\Dispatcher;

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

    public function parallelToolCalls(): bool
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
        variableRegistry: app(VariableRegistry::class),
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
    expect($request->withParallelToolCalls(false))->toBe($request);
    expect($request->forConversation(1))->toBe($request);
    expect($request->withMessageLimit(10))->toBe($request);
    expect($request->respond())->toBe($request);
    expect($request->retry())->toBe($request);
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

    // resultToChunks yields Text chunk then Done chunk
    expect($chunks)->toHaveCount(2);
    expect($chunks[0]->type)->toBe(ChunkType::Text);
    expect($chunks[0]->text)->toBe('Streamed with tools');
    expect($chunks[1]->type)->toBe(ChunkType::Done);
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
