<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Persistence\Services\ConversationService;

beforeEach(function () {
    $this->service = new ConversationService;
});

it('reconstructs full conversation history with tool calls', function () {
    $conversation = Conversation::factory()->create();

    // ── Turn 1: User asks a question ────────────────────────────
    $userMsg = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'What is the weather?'),
    );

    // ── Create execution with a step that has tool calls ────────
    $execution = Execution::factory()->completed()->create([
        'conversation_id' => $conversation->id,
    ]);

    $step = ExecutionStep::factory()->withToolCalls('Let me check the weather.')->create([
        'execution_id' => $execution->id,
        'sequence' => 0,
    ]);

    // Tool call record
    ExecutionToolCall::factory()->completed('{"temp": 72, "condition": "sunny"}')->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
        'tool_call_id' => 'call_weather_1',
        'name' => 'get_weather',
        'arguments' => ['city' => 'New York'],
    ]);

    // ── Store assistant message linked to the step ──────────────
    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Let me check the weather.', 'step_id' => $step->id]],
        agent: 'weather-bot',
        parentId: $userMsg->id,
    );

    // ── Load messages and verify reconstruction ─────────────────
    $messages = $this->service->loadMessages($conversation);

    // loadMessages returns: user message + assistant(toolCalls) + tool result = 3
    expect($messages)->toHaveCount(3);

    // Find each message type in the result set
    $userMessages = array_filter($messages, fn ($m) => $m instanceof UserMessage);
    $assistantMessages = array_filter($messages, fn ($m) => $m instanceof AssistantMessage);
    $toolResults = array_filter($messages, fn ($m) => $m instanceof ToolResultMessage);

    expect($userMessages)->toHaveCount(1)
        ->and($assistantMessages)->toHaveCount(1)
        ->and($toolResults)->toHaveCount(1);

    $user = array_values($userMessages)[0];
    $assistant = array_values($assistantMessages)[0];
    $toolResult = array_values($toolResults)[0];

    expect($user->content)->toBe('What is the weather?');

    expect($assistant->content)->toBe('Let me check the weather.')
        ->and($assistant->toolCalls)->toHaveCount(1)
        ->and($assistant->toolCalls[0])->toBeInstanceOf(ToolCall::class)
        ->and($assistant->toolCalls[0]->name)->toBe('get_weather')
        ->and($assistant->toolCalls[0]->id)->toBe('call_weather_1');

    expect($toolResult->toolCallId)->toBe('call_weather_1')
        ->and($toolResult->toolName)->toBe('get_weather')
        ->and($toolResult->content)->toBe('{"temp": 72, "condition": "sunny"}');
});

it('continues conversation with additional messages after tool calls', function () {
    $conversation = Conversation::factory()->create();

    // ── Turn 1 ──────────────────────────────────────────────────
    $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Hello!'),
    );

    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Hi! How can I help?', 'step_id' => null]],
        agent: 'bot',
    );

    // ── Turn 2 ──────────────────────────────────────────────────
    $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Tell me a joke.'),
    );

    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Why did the chicken cross the road?', 'step_id' => null]],
        agent: 'bot',
    );

    // ── Verify full history loads ───────────────────────────────
    $messages = $this->service->loadMessages($conversation);

    expect($messages)->toHaveCount(4);

    // Collect content from all messages
    $contents = array_map(fn ($m) => $m->content, $messages);

    expect($contents)->toContain('Hello!')
        ->toContain('Hi! How can I help?')
        ->toContain('Tell me a joke.')
        ->toContain('Why did the chicken cross the road?');

    // Verify correct types
    $userMessages = array_filter($messages, fn ($m) => $m instanceof UserMessage);
    $assistantMessages = array_filter($messages, fn ($m) => $m instanceof AssistantMessage);

    expect($userMessages)->toHaveCount(2)
        ->and($assistantMessages)->toHaveCount(2);
});

it('handles multiple tool calls in a single step', function () {
    $conversation = Conversation::factory()->create();

    $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Compare weather in NYC and LA'),
    );

    $execution = Execution::factory()->completed()->create([
        'conversation_id' => $conversation->id,
    ]);

    $step = ExecutionStep::factory()->withToolCalls('Let me check both cities.')->create([
        'execution_id' => $execution->id,
        'sequence' => 0,
    ]);

    ExecutionToolCall::factory()->completed('{"temp": 72}')->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
        'tool_call_id' => 'call_nyc',
        'name' => 'get_weather',
        'arguments' => ['city' => 'NYC'],
    ]);

    ExecutionToolCall::factory()->completed('{"temp": 85}')->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
        'tool_call_id' => 'call_la',
        'name' => 'get_weather',
        'arguments' => ['city' => 'LA'],
    ]);

    $this->service->addAssistantMessages(
        $conversation,
        [['text' => 'Let me check both cities.', 'step_id' => $step->id]],
        agent: 'weather-bot',
    );

    $messages = $this->service->loadMessages($conversation);

    // user + assistant(2 toolCalls) + 2 tool results = 4 messages
    expect($messages)->toHaveCount(4);

    $userMessages = array_filter($messages, fn ($m) => $m instanceof UserMessage);
    $assistantMessages = array_filter($messages, fn ($m) => $m instanceof AssistantMessage);
    $toolResults = array_filter($messages, fn ($m) => $m instanceof ToolResultMessage);

    expect($userMessages)->toHaveCount(1)
        ->and($assistantMessages)->toHaveCount(1)
        ->and($toolResults)->toHaveCount(2);

    $assistant = array_values($assistantMessages)[0];
    expect($assistant->toolCalls)->toHaveCount(2)
        ->and($assistant->content)->toBe('Let me check both cities.');

    $toolResultContents = array_map(fn ($m) => $m->content, array_values($toolResults));
    expect($toolResultContents)->toContain('{"temp": 72}')
        ->toContain('{"temp": 85}');
});
