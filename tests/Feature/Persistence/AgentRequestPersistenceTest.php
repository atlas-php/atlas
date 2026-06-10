<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\AgentRegistry;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\ConversationMessageAsset;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        config: app(AtlasConfig::class),
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

it('transfers message limit, respond mode, and retry mode to the agent', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $request = makePersistRequest('persist-conv');
    $agent = app(AgentRegistry::class)->resolve('persist-conv');

    (new ReflectionProperty($request, 'runtimeMessageLimit'))->setValue($request, 25);
    (new ReflectionProperty($request, 'respondMode'))->setValue($request, true);
    (new ReflectionProperty($request, 'retryMode'))->setValue($request, true);

    (new ReflectionMethod($request, 'transferConversationState'))->invoke($request, $agent);

    // withMessageLimit() stores the runtime override on the agent.
    $agentLimit = (new ReflectionProperty($agent, 'runtimeMessageLimit'))->getValue($agent);

    expect($agentLimit)->toBe(25)
        ->and($agent->isRespondMode())->toBeTrue()
        ->and($agent->isRetrying())->toBeTrue();
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

it('stores eager media attachments as assets linked to the message', function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('local');

    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $conversation = Conversation::factory()->create();

    $request = makePersistRequest('persist-conv')
        ->message('Look at this image', [Image::fromBase64(base64_encode('fake-png-bytes'), 'image/png')])
        ->forConversation($conversation->id);

    (new ReflectionMethod($request, 'storeUserMessageEagerly'))->invoke($request);

    $message = ConversationMessage::where('conversation_id', $conversation->id)->first();
    expect($message)->not->toBeNull();

    $asset = Asset::first();
    expect(Asset::count())->toBe(1)
        ->and($asset->type)->toBe(AssetType::Image)
        ->and($asset->mime_type)->toBe('image/png')
        ->and($asset->size_bytes)->toBe(strlen('fake-png-bytes'))
        ->and(ConversationMessageAsset::where('message_id', $message->id)->where('asset_id', $asset->id)->count())->toBe(1);

    Storage::disk($asset->disk)->assertExists($asset->path);
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
    AtlasConfig::refresh();

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

it('restores owner and message owner from a queue payload', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $owner = Conversation::factory()->create();          // morph stand-ins
    $messageOwner = Conversation::factory()->create();

    $result = AgentRequest::executeFromPayload([
        'key' => 'persist-conv',
        'message' => 'hi',
        'message_media' => [],
        'instructions' => null,
        'variables' => [],
        'meta' => [],
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'max_tokens' => null,
        'temperature' => null,
        'max_steps' => null,
        'concurrent' => null,
        'cache' => null,
        'provider_options' => [],
        'middleware' => [],
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'message_owner_type' => $messageOwner->getMorphClass(),
        'message_owner_id' => $messageOwner->getKey(),
        'conversation_id' => null,
        'message_limit' => null,
        'respond_mode' => false,
        'retry_mode' => false,
    ], 'asText');

    // The branch resolves both models via findOrFail and applies for($owner, as: $messageOwner).
    expect($result)->toBeInstanceOf(TextResponse::class);
});

// ─── Owner on user messages ───────────────────────────────────────────────

it('sets owner_type and owner_id on user message from for()', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $conversation = Conversation::factory()->create();
    $owner = Conversation::factory()->create(); // Using Conversation as a morph stand-in

    $request = makePersistRequest('persist-conv')
        ->for($owner)
        ->message('Hello from owner')
        ->forConversation($conversation->id);

    $method = new ReflectionMethod($request, 'storeUserMessageEagerly');
    $method->invoke($request);

    $message = ConversationMessage::where('conversation_id', $conversation->id)->first();

    expect($message->owner_type)->toBe($owner->getMorphClass())
        ->and($message->owner_id)->toBe($owner->getKey());
});

it('sets different owner on message when using for($owner, as: $user)', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $conversation = Conversation::factory()->create();
    $conversationOwner = Conversation::factory()->create();
    $messageAuthor = Conversation::factory()->create();

    $request = makePersistRequest('persist-conv')
        ->for($conversationOwner, as: $messageAuthor)
        ->message('Hello from different user')
        ->forConversation($conversation->id);

    $method = new ReflectionMethod($request, 'storeUserMessageEagerly');
    $method->invoke($request);

    $message = ConversationMessage::where('conversation_id', $conversation->id)->first();

    // Message owner should be the 'as' user, not the conversation owner
    expect($message->owner_type)->toBe($messageAuthor->getMorphClass())
        ->and($message->owner_id)->toBe($messageAuthor->getKey());
});

it('defaults message owner to conversation owner when as is not provided', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    $conversation = Conversation::factory()->create();
    $owner = Conversation::factory()->create();

    $request = makePersistRequest('persist-conv')
        ->for($owner)
        ->message('Hello')
        ->forConversation($conversation->id);

    $method = new ReflectionMethod($request, 'storeUserMessageEagerly');
    $method->invoke($request);

    $message = ConversationMessage::where('conversation_id', $conversation->id)->first();

    // Without 'as:', message owner defaults to conversation owner
    expect($message->owner_type)->toBe($owner->getMorphClass())
        ->and($message->owner_id)->toBe($owner->getKey());
});

// ─── storeUserMessageEagerly: HasConversations guard ───────────────────────

it('skips eager store for agents without HasConversations', function () {
    registerPersistAgent(PersistTestPlainAgent::class);
    setupPersistFake();

    $conversation = Conversation::factory()->create();

    $request = makePersistRequest('persist-plain')
        ->message('Hello')
        ->forConversation($conversation->id);

    $method = new ReflectionMethod($request, 'storeUserMessageEagerly');
    $method->invoke($request);

    // No message should be stored — agent doesn't use HasConversations
    $count = ConversationMessage::where('conversation_id', $conversation->id)->count();
    expect($count)->toBe(0);
});

// ─── storeUserMessageEagerly: error handling ───────────────────────────────

it('continues without throwing when eager store fails', function () {
    registerPersistAgent(PersistTestConversationAgent::class);
    setupPersistFake();

    // Use a non-existent conversation ID to trigger a DB error
    $request = makePersistRequest('persist-conv')
        ->message('Hello')
        ->forConversation(999999);

    $method = new ReflectionMethod($request, 'storeUserMessageEagerly');

    // Should not throw — error is caught and reported
    $method->invoke($request);

    expect(true)->toBeTrue();
});
