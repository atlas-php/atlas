<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\AgentRegistry;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\VoiceSessionFake;
use Illuminate\Contracts\Events\Dispatcher;

// ─── Test agent ─────────────────────────────────────────────────────────────

class VoiceHistoryTestAgent extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'voice-history';
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
        return 'You are a voice assistant.';
    }
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeVoiceHistoryRequest(string $key): AgentRequest
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

function createVoiceHistoryFake(): AtlasFake
{
    return new AtlasFake(app(ProviderRegistryContract::class), [
        VoiceSessionFake::make()
            ->withSessionId('history-session')
            ->withProvider('openai')
            ->withModel('gpt-4o-realtime-preview'),
    ]);
}

// ─── Setup ──────────────────────────────────────────────────────────────────

beforeEach(function () {
    app(AgentRegistry::class)->register(VoiceHistoryTestAgent::class);
    config(['atlas.persistence.enabled' => true]);
    AtlasConfig::refresh();
});

// ─── Tests ──────────────────────────────────────────────────────────────────

it('appends conversation history to voice instructions', function () {
    $fake = createVoiceHistoryFake();

    $conversation = Conversation::factory()->create();
    ConversationMessage::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'content' => 'What is your name?',
        'sequence' => 1,
    ]);
    ConversationMessage::factory()->fromAssistant('voice-history')->create([
        'conversation_id' => $conversation->id,
        'content' => 'I am a voice assistant.',
        'sequence' => 2,
    ]);

    makeVoiceHistoryRequest('voice-history')
        ->forConversation($conversation->id)
        ->asVoice();

    $recorded = $fake->recorded();
    $instructions = $recorded[0]->request->instructions;

    expect($instructions)->toContain('What is your name?')
        ->and($instructions)->toContain('I am a voice assistant.');
});

it('appends history after base instructions with double newline', function () {
    $fake = createVoiceHistoryFake();

    $conversation = Conversation::factory()->create();
    ConversationMessage::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Hello',
        'sequence' => 1,
    ]);

    makeVoiceHistoryRequest('voice-history')
        ->forConversation($conversation->id)
        ->asVoice();

    $recorded = $fake->recorded();
    $instructions = $recorded[0]->request->instructions;

    expect($instructions)->toStartWith('You are a voice assistant.')
        ->and($instructions)->toContain("\n\n");
});

it('does not append history for empty conversation', function () {
    $fake = createVoiceHistoryFake();

    $conversation = Conversation::factory()->create();

    makeVoiceHistoryRequest('voice-history')
        ->forConversation($conversation->id)
        ->asVoice();

    $recorded = $fake->recorded();
    $instructions = $recorded[0]->request->instructions;

    expect($instructions)->toBe('You are a voice assistant.');
});
