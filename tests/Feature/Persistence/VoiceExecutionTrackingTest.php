<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\VoiceSessionClosed;
use Atlasphp\Atlas\Events\VoiceToolCallRequested;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Http\StoreVoiceTranscriptController;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Voice\Http\CloseVoiceSessionController;
use Atlasphp\Atlas\Voice\Http\VoiceToolController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

// ─── Helpers ─────────────────────────────────────────────────────

class VoiceTrackingEchoTool extends Tool
{
    public function name(): string
    {
        return 'echo_test';
    }

    public function description(): string
    {
        return 'Echoes input.';
    }

    public function handle(array $args, array $context): string
    {
        return 'Echo: '.($args['message'] ?? 'empty');
    }
}

class VoiceTrackingFailTool extends Tool
{
    public function name(): string
    {
        return 'fail_tool';
    }

    public function description(): string
    {
        return 'Always fails.';
    }

    public function handle(array $args, array $context): string
    {
        throw new RuntimeException('Tool failed');
    }
}

function seedVoiceSession(
    string $sessionId,
    array $toolMap = [],
    ?int $executionId = null,
    ?int $stepId = null,
): void {
    Cache::put("voice:{$sessionId}:tools", [
        'tools' => $toolMap,
        'user_id' => 1,
        'execution_id' => $executionId,
        'step_id' => $stepId,
    ], 3600);
}

function createVoiceExecution(
    string $sessionId,
    ?int $conversationId = null,
): array {
    $execution = Execution::factory()->create([
        'type' => ExecutionType::Voice,
        'voice_session_id' => $sessionId,
        'status' => ExecutionStatus::Processing,
        'started_at' => now()->subMinutes(5),
        'conversation_id' => $conversationId,
    ]);

    $step = ExecutionStep::factory()->create([
        'execution_id' => $execution->id,
        'sequence' => 0,
        'status' => ExecutionStatus::Processing,
        'started_at' => now()->subMinutes(5),
    ]);

    return [$execution, $step];
}

function invokeVoiceToolController(string $sessionId, array $body): JsonResponse
{
    $controller = app(VoiceToolController::class);
    $request = Request::create("/voice/{$sessionId}/tool", 'POST', $body);

    return $controller($request, $sessionId);
}

function invokeCloseController(string $sessionId): Response
{
    $controller = app(CloseVoiceSessionController::class);
    $request = Request::create("/voice/{$sessionId}/close", 'POST');

    return $controller($request, $sessionId);
}

function invokeTranscriptControllerForTracking(string $sessionId, array $body): JsonResponse
{
    $controller = app(StoreVoiceTranscriptController::class);
    $request = Request::create("/voice/{$sessionId}/transcript", 'POST', $body);

    return $controller($request, $sessionId);
}

// ─── VoiceToolController — tool call tracking ───────────────────

it('creates ExecutionToolCall record on successful tool execution', function () {
    [$execution, $step] = createVoiceExecution('sess-track-1');

    seedVoiceSession('sess-track-1', [
        'echo_test' => VoiceTrackingEchoTool::class,
    ], $execution->id, $step->id);

    invokeVoiceToolController('sess-track-1', [
        'name' => 'echo_test',
        'arguments' => json_encode(['message' => 'hello']),
    ]);

    $toolCall = ExecutionToolCall::where('execution_id', $execution->id)->first();

    expect($toolCall)->not->toBeNull();
    expect($toolCall->name)->toBe('echo_test');
    expect($toolCall->status)->toBe(ExecutionStatus::Completed);
    expect($toolCall->arguments)->toBe(['message' => 'hello']);
    expect($toolCall->result)->toBe('Echo: hello');
    expect($toolCall->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('marks ExecutionToolCall as failed on tool error', function () {
    [$execution, $step] = createVoiceExecution('sess-track-2');

    seedVoiceSession('sess-track-2', [
        'fail_tool' => VoiceTrackingFailTool::class,
    ], $execution->id, $step->id);

    invokeVoiceToolController('sess-track-2', [
        'name' => 'fail_tool',
        'arguments' => '{}',
    ]);

    $toolCall = ExecutionToolCall::where('execution_id', $execution->id)->first();

    expect($toolCall)->not->toBeNull();
    expect($toolCall->status)->toBe(ExecutionStatus::Failed);
    expect($toolCall->result)->toContain('Tool failed');
});

it('skips tracking when no execution_id in cache', function () {
    seedVoiceSession('sess-no-exec', [
        'echo_test' => VoiceTrackingEchoTool::class,
    ]);

    $response = invokeVoiceToolController('sess-no-exec', [
        'name' => 'echo_test',
        'arguments' => json_encode(['message' => 'test']),
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect(ExecutionToolCall::count())->toBe(0);
});

it('fires VoiceToolCallRequested event', function () {
    Event::fake([VoiceToolCallRequested::class]);

    [$execution, $step] = createVoiceExecution('sess-event-1');

    seedVoiceSession('sess-event-1', [
        'echo_test' => VoiceTrackingEchoTool::class,
    ], $execution->id, $step->id);

    invokeVoiceToolController('sess-event-1', [
        'name' => 'echo_test',
        'arguments' => json_encode(['message' => 'test']),
    ]);

    Event::assertDispatched(VoiceToolCallRequested::class, function (VoiceToolCallRequested $event) {
        return $event->sessionId === 'sess-event-1'
            && $event->name === 'echo_test';
    });
});

// ─── StoreVoiceTranscriptController — execution completion ──────

it('marks execution as completed when storing transcript', function () {
    $conversation = Conversation::factory()->create();
    [$execution, $step] = createVoiceExecution('sess-complete-1', $conversation->id);

    invokeTranscriptControllerForTracking('sess-complete-1', [
        'conversation_id' => $conversation->id,
        'turns' => [
            ['role' => 'user', 'transcript' => 'Hello'],
            ['role' => 'assistant', 'transcript' => 'Hi there'],
        ],
    ]);

    $execution->refresh();
    $step->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect($execution->completed_at)->not->toBeNull();
    expect($execution->duration_ms)->toBeGreaterThan(0);

    expect($step->status)->toBe(ExecutionStatus::Completed);
});

it('does not fail when no execution exists for transcript', function () {
    $conversation = Conversation::factory()->create();

    $response = invokeTranscriptControllerForTracking('sess-no-exec-2', [
        'conversation_id' => $conversation->id,
        'turns' => [
            ['role' => 'user', 'transcript' => 'Hello'],
        ],
    ]);

    expect($response->getStatusCode())->toBe(200);
});

// ─── CloseVoiceSessionController ────────────────────────────────

it('marks execution as completed on close', function () {
    [$execution, $step] = createVoiceExecution('sess-close-1');

    $response = invokeCloseController('sess-close-1');

    expect($response->getStatusCode())->toBe(204);

    $execution->refresh();
    $step->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect($execution->completed_at)->not->toBeNull();
    expect($step->status)->toBe(ExecutionStatus::Completed);
});

it('is idempotent — double close does not error', function () {
    [$execution] = createVoiceExecution('sess-close-2');

    invokeCloseController('sess-close-2');
    $response = invokeCloseController('sess-close-2');

    expect($response->getStatusCode())->toBe(204);
});

it('fires VoiceSessionClosed event', function () {
    Event::fake([VoiceSessionClosed::class]);

    createVoiceExecution('sess-close-3');

    invokeCloseController('sess-close-3');

    Event::assertDispatched(VoiceSessionClosed::class, function (VoiceSessionClosed $event) {
        return $event->sessionId === 'sess-close-3';
    });
});

it('cleans up cache on close', function () {
    createVoiceExecution('sess-close-4');
    Cache::put('voice:sess-close-4:tools', ['tools' => []], 3600);

    invokeCloseController('sess-close-4');

    expect(Cache::get('voice:sess-close-4:tools'))->toBeNull();
});

it('handles close when no execution exists', function () {
    $response = invokeCloseController('nonexistent-session');

    expect($response->getStatusCode())->toBe(204);
});

// ─── Full lifecycle ─────────────────────────────────────────────

it('tracks full voice session lifecycle: session → tools → transcript', function () {
    $conversation = Conversation::factory()->create();
    [$execution, $step] = createVoiceExecution('sess-lifecycle', $conversation->id);

    seedVoiceSession('sess-lifecycle', [
        'echo_test' => VoiceTrackingEchoTool::class,
    ], $execution->id, $step->id);

    // Tool call
    invokeVoiceToolController('sess-lifecycle', [
        'name' => 'echo_test',
        'arguments' => json_encode(['message' => 'lookup']),
    ]);

    // Transcript
    invokeTranscriptControllerForTracking('sess-lifecycle', [
        'conversation_id' => $conversation->id,
        'turns' => [
            ['role' => 'user', 'transcript' => 'Check my order'],
            ['role' => 'assistant', 'transcript' => 'Your order is shipped'],
        ],
    ]);

    // Verify execution
    $execution->refresh();
    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect($execution->type)->toBe(ExecutionType::Voice);
    expect($execution->voice_session_id)->toBe('sess-lifecycle');

    // Verify tool calls
    $toolCalls = ExecutionToolCall::where('execution_id', $execution->id)->get();
    expect($toolCalls)->toHaveCount(1);
    expect($toolCalls[0]->name)->toBe('echo_test');
    expect($toolCalls[0]->status)->toBe(ExecutionStatus::Completed);

    // Verify queryable
    $found = Execution::forVoiceSession('sess-lifecycle')->with('toolCalls')->first();
    expect($found)->not->toBeNull();
    expect($found->toolCalls)->toHaveCount(1);
});

// ─── CleanStaleVoiceSessionsCommand ─────────────────────────────

it('cleans stale voice sessions beyond TTL', function () {
    $stale = Execution::factory()->create([
        'type' => ExecutionType::Voice,
        'voice_session_id' => 'sess-stale-1',
        'status' => ExecutionStatus::Processing,
        'started_at' => now()->subMinutes(120),
    ]);

    $fresh = Execution::factory()->create([
        'type' => ExecutionType::Voice,
        'voice_session_id' => 'sess-fresh-1',
        'status' => ExecutionStatus::Processing,
        'started_at' => now()->subMinutes(5),
    ]);

    $this->artisan('atlas:clean-voice-sessions', ['--ttl' => 60])
        ->assertExitCode(0);

    $stale->refresh();
    $fresh->refresh();

    expect($stale->status)->toBe(ExecutionStatus::Completed);
    expect($stale->metadata)->toHaveKey('stale_cleanup', true);
    expect($fresh->status)->toBe(ExecutionStatus::Processing);
});

it('does not clean non-voice executions', function () {
    $textExec = Execution::factory()->create([
        'type' => ExecutionType::Text,
        'status' => ExecutionStatus::Processing,
        'started_at' => now()->subMinutes(120),
    ]);

    $this->artisan('atlas:clean-voice-sessions', ['--ttl' => 60])
        ->assertExitCode(0);

    $textExec->refresh();
    expect($textExec->status)->toBe(ExecutionStatus::Processing);
});

it('skips when persistence is disabled', function () {
    config(['atlas.persistence.enabled' => false]);

    $this->artisan('atlas:clean-voice-sessions')
        ->assertExitCode(0)
        ->expectsOutput('Persistence is not enabled. Nothing to clean.');
});
