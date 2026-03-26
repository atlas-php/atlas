<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Events\VoiceSessionClosed;
use Atlasphp\Atlas\Events\VoiceToolCallStarted;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Enums\VoiceCallStatus;
use Atlasphp\Atlas\Persistence\Http\StoreVoiceTranscriptController;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Persistence\Models\VoiceCall;
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
): void {
    Cache::put("voice:{$sessionId}:tools", [
        'tools' => $toolMap,
        'user_id' => 1,
        'execution_id' => $executionId,
    ], 3600);
}

function createVoiceExecution(
    string $sessionId,
    ?int $conversationId = null,
): Execution {
    return Execution::factory()->create([
        'type' => ExecutionType::Voice,
        'status' => ExecutionStatus::Processing,
        'started_at' => now()->subMinutes(5),
        'conversation_id' => $conversationId,
    ]);
}

function invokeVoiceToolController(string $sessionId, array $body): JsonResponse
{
    $controller = app(VoiceToolController::class);
    $request = Request::create("/voice/{$sessionId}/tool", 'POST', $body);

    return $controller($request, $sessionId);
}

function invokeCloseController(string $sessionId, array $body = []): Response|JsonResponse
{
    $controller = app(CloseVoiceSessionController::class);
    $request = Request::create("/voice/{$sessionId}/close", 'POST', $body);

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
    $execution = createVoiceExecution('sess-track-1');

    seedVoiceSession('sess-track-1', [
        'echo_test' => VoiceTrackingEchoTool::class,
    ], $execution->id);

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
    $execution = createVoiceExecution('sess-track-2');

    seedVoiceSession('sess-track-2', [
        'fail_tool' => VoiceTrackingFailTool::class,
    ], $execution->id);

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

it('fires VoiceToolCallStarted event', function () {
    Event::fake([VoiceToolCallStarted::class]);

    $execution = createVoiceExecution('sess-event-1');

    seedVoiceSession('sess-event-1', [
        'echo_test' => VoiceTrackingEchoTool::class,
    ], $execution->id);

    invokeVoiceToolController('sess-event-1', [
        'name' => 'echo_test',
        'arguments' => json_encode(['message' => 'test']),
    ]);

    Event::assertDispatched(VoiceToolCallStarted::class, function (VoiceToolCallStarted $event) {
        return $event->sessionId === 'sess-event-1'
            && $event->name === 'echo_test';
    });
});

// ─── StoreVoiceTranscriptController — VoiceCall storage ─────────

it('saves transcript to VoiceCall record', function () {
    $execution = createVoiceExecution('sess-vc-1');

    VoiceCall::create([
        'voice_session_id' => 'sess-vc-1',
        'provider' => 'xai',
        'model' => 'grok-3',
        'status' => VoiceCallStatus::Active,
        'transcript' => [],
        'started_at' => now(),
    ]);

    $response = invokeTranscriptControllerForTracking('sess-vc-1', [
        'turns' => [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there'],
        ],
    ]);

    expect($response->getStatusCode())->toBe(200);

    $call = VoiceCall::where('voice_session_id', 'sess-vc-1')->first();
    expect($call->transcript)->toHaveCount(2);
});

it('returns 404 when voice call not found for transcript', function () {
    $response = invokeTranscriptControllerForTracking('nonexistent', [
        'turns' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    ]);

    expect($response->getStatusCode())->toBe(404);
});

// ─── CloseVoiceSessionController ────────────────────────────────

it('marks execution and voice call as completed on close', function () {
    $execution = createVoiceExecution('sess-close-1');

    $call = VoiceCall::create([
        'voice_session_id' => 'sess-close-1',
        'provider' => 'xai',
        'model' => 'grok-3',
        'status' => VoiceCallStatus::Active,
        'transcript' => [['role' => 'user', 'content' => 'Hello']],
        'started_at' => now()->subMinutes(5),
    ]);

    $call->update(['execution_id' => $execution->id]);

    $response = invokeCloseController('sess-close-1');

    expect($response->getStatusCode())->toBe(204);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect($execution->completed_at)->not->toBeNull();

    $call = VoiceCall::where('voice_session_id', 'sess-close-1')->first();
    expect($call->status)->toBe(VoiceCallStatus::Completed);
    expect($call->completed_at)->not->toBeNull();
});

it('is idempotent — double close does not error', function () {
    $execution = createVoiceExecution('sess-close-2');

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

it('tracks full voice session lifecycle: session → tools → transcript → close', function () {
    $conversation = Conversation::factory()->create();
    $execution = createVoiceExecution('sess-lifecycle', $conversation->id);

    // Create VoiceCall record and link execution
    $call = VoiceCall::create([
        'voice_session_id' => 'sess-lifecycle',
        'conversation_id' => $conversation->id,
        'provider' => 'xai',
        'model' => 'grok-3',
        'status' => VoiceCallStatus::Active,
        'transcript' => [],
        'started_at' => now(),
    ]);

    $call->update(['execution_id' => $execution->id]);

    seedVoiceSession('sess-lifecycle', [
        'echo_test' => VoiceTrackingEchoTool::class,
    ], $execution->id);

    // Tool call
    invokeVoiceToolController('sess-lifecycle', [
        'name' => 'echo_test',
        'arguments' => json_encode(['message' => 'lookup']),
    ]);

    // Checkpoint transcript
    invokeTranscriptControllerForTracking('sess-lifecycle', [
        'turns' => [
            ['role' => 'user', 'content' => 'Check my order'],
            ['role' => 'assistant', 'content' => 'Your order is shipped'],
        ],
    ]);

    // Close session
    invokeCloseController('sess-lifecycle');

    // Verify execution completed
    $execution->refresh();
    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect($execution->type)->toBe(ExecutionType::Voice);

    // Verify tool calls tracked
    $toolCalls = ExecutionToolCall::where('execution_id', $execution->id)->get();
    expect($toolCalls)->toHaveCount(1);
    expect($toolCalls[0]->name)->toBe('echo_test');

    // Verify VoiceCall has transcript
    $call = VoiceCall::where('voice_session_id', 'sess-lifecycle')->first();
    expect($call->status)->toBe(VoiceCallStatus::Completed);
    expect($call->transcript)->toHaveCount(2);
    expect($call->duration_ms)->toBeGreaterThan(0);
});

// ─── CleanStaleVoiceSessionsCommand ─────────────────────────────

it('cleans stale voice calls beyond TTL', function () {
    $staleExec = Execution::factory()->create([
        'type' => ExecutionType::Voice,
        'status' => ExecutionStatus::Processing,
        'started_at' => now()->subMinutes(120),
    ]);

    $staleCall = VoiceCall::create([
        'voice_session_id' => 'sess-stale-1',
        'provider' => 'xai',
        'model' => 'grok-3',
        'status' => VoiceCallStatus::Active,
        'transcript' => [['role' => 'user', 'content' => 'Hello']],
        'started_at' => now()->subMinutes(120),
    ]);

    $staleCall->update(['execution_id' => $staleExec->id]);

    VoiceCall::create([
        'voice_session_id' => 'sess-fresh-1',
        'provider' => 'xai',
        'model' => 'grok-3',
        'status' => VoiceCallStatus::Active,
        'transcript' => [],
        'started_at' => now()->subMinutes(5),
    ]);

    $this->artisan('atlas:clean-voice-sessions', ['--ttl' => 60])
        ->assertExitCode(0);

    $staleCall = VoiceCall::where('voice_session_id', 'sess-stale-1')->first();
    $freshCall = VoiceCall::where('voice_session_id', 'sess-fresh-1')->first();

    expect($staleCall->status)->toBe(VoiceCallStatus::Completed);
    expect($freshCall->status)->toBe(VoiceCallStatus::Active);

    $staleExec->refresh();
    expect($staleExec->status)->toBe(ExecutionStatus::Completed);
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
    AtlasConfig::refresh();

    $this->artisan('atlas:clean-voice-sessions')
        ->assertExitCode(0)
        ->expectsOutput('Persistence is not enabled. Nothing to clean.');
});
