<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Models\Conversation;

function makeConversationAgent(): Agent
{
    return new class extends Agent
    {
        use HasConversations;

        public function key(): string
        {
            return 'test-agent';
        }
    };
}

it('for() sets conversation owner and returns self', function () {
    $agent = makeConversationAgent();
    $owner = Conversation::factory()->create();

    $result = $agent->for($owner);

    expect($result)->toBe($agent)
        ->and($agent->resolveAuthor())->toBe($owner);
});

it('asUser() sets message author and returns self', function () {
    $agent = makeConversationAgent();
    $author = Conversation::factory()->create();

    $result = $agent->asUser($author);

    expect($result)->toBe($agent)
        ->and($agent->resolveAuthor())->toBe($author);
});

it('resolveAuthor returns explicit author when set', function () {
    $agent = makeConversationAgent();
    $owner = Conversation::factory()->create();
    $author = Conversation::factory()->create();

    $agent->for($owner)->asUser($author);

    expect($agent->resolveAuthor())->toBe($author);
});

it('resolveAuthor falls back to owner when no explicit author', function () {
    $agent = makeConversationAgent();
    $owner = Conversation::factory()->create();

    $agent->for($owner);

    expect($agent->resolveAuthor())->toBe($owner);
});

it('forConversation() sets conversation ID', function () {
    $agent = makeConversationAgent();
    $conversation = Conversation::factory()->create();

    $result = $agent->forConversation($conversation->id);

    expect($result)->toBe($agent);

    $resolved = $agent->resolveConversation();

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($conversation->id);
});

it('respond() sets respond mode', function () {
    $agent = makeConversationAgent();

    expect($agent->isRespondMode())->toBeFalse();

    $result = $agent->respond();

    expect($result)->toBe($agent)
        ->and($agent->isRespondMode())->toBeTrue();
});

it('retry() sets retry mode', function () {
    $agent = makeConversationAgent();

    expect($agent->isRetrying())->toBeFalse();

    $result = $agent->retry();

    expect($result)->toBe($agent)
        ->and($agent->isRetrying())->toBeTrue();
});

it('setRetryParentId stores parent ID', function () {
    $agent = makeConversationAgent();

    expect($agent->getRetryParentId())->toBeNull();

    $agent->setRetryParentId(42);

    expect($agent->getRetryParentId())->toBe(42);
});

it('withMessageLimit overrides message limit', function () {
    $agent = makeConversationAgent();

    $result = $agent->withMessageLimit(20);

    expect($result)->toBe($agent);
});

it('resolveMessageLimit uses runtime override first', function () {
    $agent = makeConversationAgent();
    $agent->withMessageLimit(10);

    // Use reflection to call the protected method
    $reflection = new ReflectionMethod($agent, 'resolveMessageLimit');
    $reflection->setAccessible(true);

    expect($reflection->invoke($agent))->toBe(10);
});

it('resolveMessageLimit falls back to config', function () {
    config()->set('atlas.persistence.message_limit', 75);

    $agent = makeConversationAgent();

    $reflection = new ReflectionMethod($agent, 'resolveMessageLimit');
    $reflection->setAccessible(true);

    expect($reflection->invoke($agent))->toBe(75);
});

it('resolveConversation creates conversation via for()', function () {
    $agent = makeConversationAgent();
    $owner = Conversation::factory()->create();

    $agent->for($owner);
    $conversation = $agent->resolveConversation();

    expect($conversation)->not->toBeNull()
        ->and($conversation)->toBeInstanceOf(Conversation::class)
        ->and($conversation->exists)->toBeTrue()
        ->and($conversation->agent)->toBe('test-agent');
});

it('resolveConversation finds existing conversation via forConversation()', function () {
    $agent = makeConversationAgent();
    $existing = Conversation::factory()->create();

    $agent->forConversation($existing->id);
    $conversation = $agent->resolveConversation();

    expect($conversation)->not->toBeNull()
        ->and($conversation->id)->toBe($existing->id);
});

it('resolveConversation returns null when nothing configured', function () {
    $agent = makeConversationAgent();

    $conversation = $agent->resolveConversation();

    expect($conversation)->toBeNull();
});
