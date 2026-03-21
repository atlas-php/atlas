<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Persistence\Models\Message;
use Atlasphp\Atlas\Persistence\Services\ConversationService;

beforeEach(function () {
    $this->service = new ConversationService;
});

// ─── Test 1: Full retry lifecycle with sequence preservation ─────────

it('performs a full retry lifecycle preserving sequences', function () {
    $conversation = Conversation::factory()->create();

    // seq 0: user message
    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Explain quantum computing'),
    );

    // seq 1: assistant "Response A"
    $assistantMessages = $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Response A', 'step_id' => null]],
        agent: 'bot',
        parentId: $userMsg->id,
    );
    $responseA = $assistantMessages[0];

    expect($responseA->is_active)->toBeTrue()
        ->and($responseA->sequence)->toBe(1);

    // prepareRetry deactivates seq 1
    $parentId = $this->service->prepareRetry($conversation);

    expect($parentId)->toBe($userMsg->id);

    // Refresh to see deactivation
    $responseA->refresh();
    expect($responseA->is_active)->toBeFalse();

    // seq 2: assistant "Response B"
    $newMessages = $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Response B', 'step_id' => null]],
        agent: 'bot',
        parentId: $parentId,
    );
    $responseB = $newMessages[0];

    expect($responseB->is_active)->toBeTrue()
        ->and($responseB->sequence)->toBe(2)
        ->and($responseB->parent_id)->toBe($userMsg->id)
        ->and($responseA->parent_id)->toBe($userMsg->id);

    // Sequences are 0, 1, 2 — no gaps
    $allSequences = Message::where('conversation_id', $conversation->id)
        ->orderBy('sequence')
        ->pluck('sequence')
        ->all();
    expect($allSequences)->toBe([0, 1, 2]);

    // recentMessages returns user + Response B, not Response A
    $recent = $conversation->recentMessages();
    expect($recent)->toHaveCount(2);
    $recentContents = $recent->pluck('content')->all();
    expect($recentContents)->toContain('Explain quantum computing')
        ->toContain('Response B')
        ->not->toContain('Response A');
});

// ─── Test 2: Active state toggling via cycleSibling ─────────────────

it('toggles active state so loadMessages returns only the active sibling', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Hello'),
    );

    // Response A (will become inactive after retry)
    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Response A', 'step_id' => null]],
        agent: 'bot',
        parentId: $userMsg->id,
    );

    // Retry → deactivates Response A
    $parentId = $this->service->prepareRetry($conversation);

    // Response B (active)
    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Response B', 'step_id' => null]],
        agent: 'bot',
        parentId: $parentId,
    );

    // loadMessages should return user + Response B
    $messages = $this->service->loadMessages($conversation);
    expect($messages)->toHaveCount(2);
    $contents = array_map(fn ($m) => $m->content, $messages);
    expect($contents)->toContain('Response B')
        ->not->toContain('Response A');

    // cycleSibling to group 1 → activate Response A
    $this->service->cycleSibling($conversation, $parentId, 1);

    $messages = $this->service->loadMessages($conversation);
    expect($messages)->toHaveCount(2);
    $contents = array_map(fn ($m) => $m->content, $messages);
    expect($contents)->toContain('Response A')
        ->not->toContain('Response B');

    // cycleSibling to group 2 → re-activate Response B
    $this->service->cycleSibling($conversation, $parentId, 2);

    $messages = $this->service->loadMessages($conversation);
    expect($messages)->toHaveCount(2);
    $contents = array_map(fn ($m) => $m->content, $messages);
    expect($contents)->toContain('Response B')
        ->not->toContain('Response A');
});

// ─── Test 3: Three retry groups — cycle through all ─────────────────

it('cycles through three retry groups correctly', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Question'),
    );

    // R1
    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'R1', 'step_id' => null]],
        agent: 'bot',
        parentId: $userMsg->id,
    );
    $this->service->prepareRetry($conversation);

    // R2
    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'R2', 'step_id' => null]],
        agent: 'bot',
        parentId: $userMsg->id,
    );
    $this->service->prepareRetry($conversation);

    // R3 (active)
    $r3Messages = $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'R3', 'step_id' => null]],
        agent: 'bot',
        parentId: $userMsg->id,
    );
    $r3 = $r3Messages[0];

    // siblingInfo on R3 → current=3, total=3
    $info = $this->service->siblingInfo($r3);
    expect($info['current'])->toBe(3)
        ->and($info['total'])->toBe(3);

    // recentMessages shows R3
    $recent = $conversation->recentMessages();
    expect($recent)->toHaveCount(2);
    $recentContents = $recent->pluck('content')->all();
    expect($recentContents)->toContain('R3')
        ->not->toContain('R1')
        ->not->toContain('R2');

    // cycleSibling to group 1 → R1 active
    $this->service->cycleSibling($conversation, $userMsg->id, 1);

    $r1 = Message::where('conversation_id', $conversation->id)
        ->where('content', 'R1')
        ->first();
    $infoR1 = $this->service->siblingInfo($r1);
    expect($infoR1['current'])->toBe(1)
        ->and($infoR1['total'])->toBe(3);

    $recent = $conversation->recentMessages();
    expect($recent)->toHaveCount(2);
    $recentContents = $recent->pluck('content')->all();
    expect($recentContents)->toContain('R1')
        ->not->toContain('R2')
        ->not->toContain('R3');

    // cycleSibling to group 2 → R2 active
    $this->service->cycleSibling($conversation, $userMsg->id, 2);

    $recent = $conversation->recentMessages();
    expect($recent)->toHaveCount(2);
    $recentContents = $recent->pluck('content')->all();
    expect($recentContents)->toContain('R2')
        ->not->toContain('R1')
        ->not->toContain('R3');
});

// ─── Test 4: Parent_id consistency across retries ───────────────────

it('maintains parent_id consistency across multiple retries', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Original question'),
    );

    // Response 1
    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Answer 1', 'step_id' => null]],
        agent: 'bot',
        parentId: $userMsg->id,
    );

    // Retry 1 → get parentId
    $parentId1 = $this->service->prepareRetry($conversation);

    // Response 2
    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Answer 2', 'step_id' => null]],
        agent: 'bot',
        parentId: $parentId1,
    );

    // Retry 2 → get parentId again
    $parentId2 = $this->service->prepareRetry($conversation);

    // Response 3
    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Answer 3', 'step_id' => null]],
        agent: 'bot',
        parentId: $parentId2,
    );

    // All parentIds should be the same
    expect($parentId1)->toBe($userMsg->id)
        ->and($parentId2)->toBe($userMsg->id);

    // All three assistant messages have identical parent_id pointing to user message
    $parentIds = Message::where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Assistant)
        ->pluck('parent_id')
        ->unique()
        ->all();

    expect($parentIds)->toHaveCount(1)
        ->and($parentIds[0])->toBe($userMsg->id);
});

// ─── Test 5: canRetry edge cases ────────────────────────────────────

it('returns true for canRetry when no later active user message exists', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Hello'),
    );

    $assistantMessages = $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Hi there', 'step_id' => null]],
        agent: 'bot',
        parentId: $userMsg->id,
    );

    expect($assistantMessages[0]->canRetry())->toBeTrue();
});

it('returns false for canRetry when a later active user message exists', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Hello'),
    );

    $assistantMessages = $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Hi there', 'step_id' => null]],
        agent: 'bot',
        parentId: $userMsg->id,
    );

    // User sends a follow-up
    $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Follow-up question'),
    );

    // Now canRetry should be false — conversation has continued
    expect($assistantMessages[0]->canRetry())->toBeFalse();
});

it('returns false for canRetry on user messages', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Hello'),
    );

    expect($userMsg->canRetry())->toBeFalse();
});

it('returns true for canRetry on inactive sibling when no later user message exists', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Hello'),
    );

    // R1 (will become inactive)
    $r1Messages = $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'R1', 'step_id' => null]],
        agent: 'bot',
        parentId: $userMsg->id,
    );

    $this->service->prepareRetry($conversation);

    // R2 (active)
    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'R2', 'step_id' => null]],
        agent: 'bot',
        parentId: $userMsg->id,
    );

    $r1 = $r1Messages[0]->fresh();

    // canRetry checks for later active user messages, not is_active on self
    // No user message after R1's sequence → true
    expect($r1->canRetry())->toBeTrue();
});

// ─── Test 6: loadMessages excludes inactive siblings with tool reconstruction ─

it('excludes inactive sibling tool messages from loadMessages', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Look something up'),
    );

    // ── Execution A (will become inactive) ──────────────────────
    $executionA = Execution::factory()->completed()->create([
        'conversation_id' => $conversation->id,
    ]);

    $stepA = ExecutionStep::factory()->withToolCalls('Looking it up...')->create([
        'execution_id' => $executionA->id,
        'sequence' => 0,
    ]);

    ExecutionToolCall::factory()->completed('{"result": "data A"}')->create([
        'execution_id' => $executionA->id,
        'step_id' => $stepA->id,
        'tool_call_id' => 'call_a',
        'name' => 'search',
        'arguments' => ['q' => 'test'],
    ]);

    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Looking it up...', 'step_id' => $stepA->id]],
        agent: 'bot',
        parentId: $userMsg->id,
    );

    // Retry → deactivates execution A messages
    $parentId = $this->service->prepareRetry($conversation);

    // ── Execution B (active) ────────────────────────────────────
    $executionB = Execution::factory()->completed()->create([
        'conversation_id' => $conversation->id,
    ]);

    $stepB = ExecutionStep::factory()->withToolCalls('Let me check...')->create([
        'execution_id' => $executionB->id,
        'sequence' => 0,
    ]);

    ExecutionToolCall::factory()->completed('{"result": "data B"}')->create([
        'execution_id' => $executionB->id,
        'step_id' => $stepB->id,
        'tool_call_id' => 'call_b',
        'name' => 'search',
        'arguments' => ['q' => 'test'],
    ]);

    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Let me check...', 'step_id' => $stepB->id]],
        agent: 'bot',
        parentId: $parentId,
    );

    // loadMessages should only include execution B messages
    $messages = $this->service->loadMessages($conversation);

    $contents = array_map(fn ($m) => $m->content, $messages);

    // Should contain: user message, assistant "Let me check..." (with tool calls), tool result
    expect($messages)->toHaveCount(3);
    expect($contents)->toContain('Look something up')
        ->toContain('Let me check...')
        ->toContain('{"result": "data B"}');

    // Should NOT contain execution A content
    expect($contents)->not->toContain('Looking it up...')
        ->not->toContain('{"result": "data A"}');
});

// ─── Test 7: Sequence increments correctly with addAssistantMessages during retry ─

it('increments sequences correctly across retries with no gaps or collisions', function () {
    $conversation = Conversation::factory()->create();

    // seq 0: user message
    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Question'),
    );

    // seq 1, 2: two assistant messages
    $first = $this->service->addAssistantMessages(
        $conversation,
        [
            ['text' => 'Step 1 of answer', 'step_id' => null],
            ['text' => 'Step 2 of answer', 'step_id' => null],
        ],
        agent: 'bot',
        parentId: $userMsg->id,
    );

    expect($first[0]->sequence)->toBe(1)
        ->and($first[1]->sequence)->toBe(2);

    // Retry → deactivates seq 1, 2
    $this->service->prepareRetry($conversation);

    // seq 3, 4: two more assistant messages
    $second = $this->service->addAssistantMessages(
        $conversation,
        [
            ['text' => 'New step 1', 'step_id' => null],
            ['text' => 'New step 2', 'step_id' => null],
        ],
        agent: 'bot',
        parentId: $userMsg->id,
    );

    expect($second[0]->sequence)->toBe(3)
        ->and($second[1]->sequence)->toBe(4);

    // Verify all sequences: 0, 1, 2, 3, 4
    $allSequences = Message::where('conversation_id', $conversation->id)
        ->orderBy('sequence')
        ->pluck('sequence')
        ->all();
    expect($allSequences)->toBe([0, 1, 2, 3, 4]);

    // nextSequence should be 5
    expect($conversation->nextSequence())->toBe(5);
});

// ─── Test 8: siblingGroups groups multi-step responses by execution ──

it('groups multi-step responses as single sibling groups', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Complex question'),
    );

    // ── Execution A: 2-step response (tool call + final answer) ──
    $executionA = Execution::factory()->completed()->create([
        'conversation_id' => $conversation->id,
    ]);

    $stepA1 = ExecutionStep::factory()->withToolCalls('Looking up...')->create([
        'execution_id' => $executionA->id,
        'sequence' => 0,
    ]);
    $stepA2 = ExecutionStep::factory()->completed('Here is the answer A.')->create([
        'execution_id' => $executionA->id,
        'sequence' => 1,
    ]);

    $this->service->addAssistantMessages(
        $conversation,
        [
            ['text' => 'Looking up...', 'step_id' => $stepA1->id],
            ['text' => 'Here is the answer A.', 'step_id' => $stepA2->id],
        ],
        agent: 'bot',
        parentId: $userMsg->id,
    );

    // Retry → deactivates execution A messages
    $parentId = $this->service->prepareRetry($conversation);

    // ── Execution B: 2-step response ──
    $executionB = Execution::factory()->completed()->create([
        'conversation_id' => $conversation->id,
    ]);

    $stepB1 = ExecutionStep::factory()->withToolCalls('Searching...')->create([
        'execution_id' => $executionB->id,
        'sequence' => 0,
    ]);
    $stepB2 = ExecutionStep::factory()->completed('Here is the answer B.')->create([
        'execution_id' => $executionB->id,
        'sequence' => 1,
    ]);

    $this->service->addAssistantMessages(
        $conversation,
        [
            ['text' => 'Searching...', 'step_id' => $stepB1->id],
            ['text' => 'Here is the answer B.', 'step_id' => $stepB2->id],
        ],
        agent: 'bot',
        parentId: $parentId,
    );

    // Should be 2 groups, not 4
    $anyChild = Message::where('parent_id', $userMsg->id)->first();
    $groups = $anyChild->siblingGroups();

    expect($groups)->toHaveCount(2)
        ->and($groups[0])->toHaveCount(2)
        ->and($groups[1])->toHaveCount(2);

    // siblingInfo reflects correct grouping
    $activeMessage = Message::where('parent_id', $userMsg->id)
        ->where('is_active', true)
        ->first();
    $info = $this->service->siblingInfo($activeMessage);

    expect($info['current'])->toBe(2)
        ->and($info['total'])->toBe(2);
});

// ─── Test 9: cycleSibling with multi-step responses ────────────────

it('cycleSibling activates all messages in a multi-step group', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Multi-step question'),
    );

    // ── Execution A: 2 steps ──
    $executionA = Execution::factory()->completed()->create([
        'conversation_id' => $conversation->id,
    ]);

    $stepA1 = ExecutionStep::factory()->completed('Step 1 of A')->create([
        'execution_id' => $executionA->id,
        'sequence' => 0,
    ]);
    $stepA2 = ExecutionStep::factory()->completed('Step 2 of A')->create([
        'execution_id' => $executionA->id,
        'sequence' => 1,
    ]);

    $this->service->addAssistantMessages(
        $conversation,
        [
            ['text' => 'Step 1 of A', 'step_id' => $stepA1->id],
            ['text' => 'Step 2 of A', 'step_id' => $stepA2->id],
        ],
        agent: 'bot',
        parentId: $userMsg->id,
    );

    // Retry
    $parentId = $this->service->prepareRetry($conversation);

    // ── Execution B: 2 steps ──
    $executionB = Execution::factory()->completed()->create([
        'conversation_id' => $conversation->id,
    ]);

    $stepB1 = ExecutionStep::factory()->completed('Step 1 of B')->create([
        'execution_id' => $executionB->id,
        'sequence' => 0,
    ]);
    $stepB2 = ExecutionStep::factory()->completed('Step 2 of B')->create([
        'execution_id' => $executionB->id,
        'sequence' => 1,
    ]);

    $this->service->addAssistantMessages(
        $conversation,
        [
            ['text' => 'Step 1 of B', 'step_id' => $stepB1->id],
            ['text' => 'Step 2 of B', 'step_id' => $stepB2->id],
        ],
        agent: 'bot',
        parentId: $parentId,
    );

    // Active should be B (both steps)
    $active = Message::where('conversation_id', $conversation->id)
        ->where('parent_id', $userMsg->id)
        ->where('is_active', true)
        ->pluck('content')
        ->all();
    expect($active)->toBe(['Step 1 of B', 'Step 2 of B']);

    // Cycle to group 1 → both A steps should activate
    $this->service->cycleSibling($conversation, $userMsg->id, 1);

    $active = Message::where('conversation_id', $conversation->id)
        ->where('parent_id', $userMsg->id)
        ->where('is_active', true)
        ->pluck('content')
        ->all();
    expect($active)->toBe(['Step 1 of A', 'Step 2 of A']);

    // loadMessages should include both steps of A
    $messages = $this->service->loadMessages($conversation);
    $contents = array_map(fn ($m) => $m->content, $messages);
    expect($contents)->toContain('Step 1 of A')
        ->toContain('Step 2 of A')
        ->not->toContain('Step 1 of B')
        ->not->toContain('Step 2 of B');
});
