<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Agents\AgentRegistry;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

// ─── Test agents ────────────────────────────────────────────────────────────

class PersistTestConversationAgent extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'persist-conv';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }
}

class PersistTestPlainAgent extends Agent
{
    public function key(): string
    {
        return 'persist-plain';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function makePersistRequest(string $key): AgentRequest
{
    return new AgentRequest(
        key: $key,
        agentRegistry: app(AgentRegistry::class),
        providerRegistry: app(ProviderRegistryContract::class),
        app: app(),
        events: app(Dispatcher::class),
    );
}

function registerPersistAgent(string $agentClass): void
{
    app(AgentRegistry::class)->register($agentClass);
}

function setupPersistFake(): AtlasFake
{
    return new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('OK'),
    ]);
}

// ─── transferConversationState ─────────────────────────────────────────────

it('transfers conversation owner to agent', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $request = makePersistRequest('persist-conv');

    // Use reflection to access the agent after resolveAgent + transferConversationState
    $agent = app(AgentRegistry::class)->resolve('persist-conv');
    $method = new ReflectionMethod($request, 'transferConversationState');

    // Create a fake owner model
    $owner = Conversation::factory()->create();

    // Set conversationOwner via reflection
    $ownerProp = new ReflectionProperty($request, 'conversationOwner');
    $ownerProp->setValue($request, $owner);

    $convIdProp = new ReflectionProperty($request, 'conversationId');
    $convIdProp->setValue($request, 42);

    $method->invoke($request, $agent);

    // Agent should have conversation state transferred
    $agentConvProp = new ReflectionProperty($agent, 'conversationId');
    expect($agentConvProp->getValue($agent))->toBe(42);
});

it('skips transfer for agents without HasConversations', function () {
    registerPersistAgent(PersistTestPlainAgent::class);
    setupPersistFake();

    $request = makePersistRequest('persist-plain');
    $agent = app(AgentRegistry::class)->resolve('persist-plain');

    $method = new ReflectionMethod($request, 'transferConversationState');

    // Should not throw — just returns early
    $method->invoke($request, $agent);

    // No error means pass
    expect(true)->toBeTrue();
});

// ─── storeUserMessageEagerly ───────────────────────────────────────────────

it('stores user message to conversation eagerly', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $conversation = Conversation::factory()->create();

    $request = makePersistRequest('persist-conv')
        ->message('Hello world')
        ->forConversation($conversation->id);

    $method = new ReflectionMethod($request, 'storeUserMessageEagerly');
    $method->invoke($request);

    // Should have stored the message
    $messages = ConversationMessage::where('conversation_id', $conversation->id)->get();
    expect($messages)->toHaveCount(1);
    expect($messages->first()->role->value)->toBe('user');
});

it('sets conversation title from first user message', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $conversation = Conversation::factory()->create(['title' => null]);

    $request = makePersistRequest('persist-conv')
        ->message('What is the weather today?')
        ->forConversation($conversation->id);

    $method = new ReflectionMethod($request, 'storeUserMessageEagerly');
    $method->invoke($request);

    $conversation->refresh();
    expect($conversation->title)->toBe('What is the weather today?');
});

it('skips eager store when persistence is disabled', function () {
    config(['atlas.persistence.enabled' => false]);

    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $conversation = Conversation::factory()->create();

    $request = makePersistRequest('persist-conv')
        ->message('Hello')
        ->forConversation($conversation->id);

    $method = new ReflectionMethod($request, 'storeUserMessageEagerly');
    $method->invoke($request);

    $messages = ConversationMessage::where('conversation_id', $conversation->id)->count();
    expect($messages)->toBe(0);
});

it('skips eager store when no message is set', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $conversation = Conversation::factory()->create();

    $request = makePersistRequest('persist-conv')
        ->forConversation($conversation->id);

    $method = new ReflectionMethod($request, 'storeUserMessageEagerly');
    $method->invoke($request);

    $messages = ConversationMessage::where('conversation_id', $conversation->id)->count();
    expect($messages)->toBe(0);
});

it('skips eager store when no conversationId is set', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $request = makePersistRequest('persist-conv')
        ->message('Hello');

    $method = new ReflectionMethod($request, 'storeUserMessageEagerly');
    $method->invoke($request);

    $messages = ConversationMessage::count();
    expect($messages)->toBe(0);
});

it('skips eager store in respond mode', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $conversation = Conversation::factory()->create();

    $request = makePersistRequest('persist-conv')
        ->message('Hello')
        ->forConversation($conversation->id)
        ->respond();

    $method = new ReflectionMethod($request, 'storeUserMessageEagerly');
    $method->invoke($request);

    $messages = ConversationMessage::where('conversation_id', $conversation->id)->count();
    expect($messages)->toBe(0);
});

it('switches to respond mode after eager store', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $conversation = Conversation::factory()->create();

    $request = makePersistRequest('persist-conv')
        ->message('Hello')
        ->forConversation($conversation->id);

    $method = new ReflectionMethod($request, 'storeUserMessageEagerly');
    $method->invoke($request);

    $respondProp = new ReflectionProperty($request, 'respondMode');
    expect($respondProp->getValue($request))->toBeTrue();
});
