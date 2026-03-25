<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\VoiceCallCompleted;
use Atlasphp\Atlas\Events\VoiceSessionClosed;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Enums\VoiceCallStatus;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\VoiceCall;
use Atlasphp\Atlas\Voice\Http\CloseVoiceSessionController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

// ─── Helpers ─────────────────────────────────────────────────────

function createCloseTestVoiceCall(
    string $sessionId,
    array $overrides = [],
): VoiceCall {
    return VoiceCall::create(array_merge([
        'voice_session_id' => $sessionId,
        'provider' => 'xai',
        'model' => 'grok-3',
        'status' => VoiceCallStatus::Active,
        'transcript' => [],
        'started_at' => now()->subMinutes(5),
    ], $overrides));
}

function createCloseTestExecution(
    string $sessionId,
    ?int $voiceCallId = null,
): Execution {
    $execution = Execution::factory()->create([
        'type' => ExecutionType::Voice,
        'status' => ExecutionStatus::Processing,
        'started_at' => now()->subMinutes(5),
    ]);

    // Link voice call to execution (VoiceCall owns the FK)
    if ($voiceCallId !== null) {
        VoiceCall::where('id', $voiceCallId)->update(['execution_id' => $execution->id]);
    }

    return $execution;
}

function invokeClose(string $sessionId, array $body = []): Response|JsonResponse
{
    $controller = app(CloseVoiceSessionController::class);
    $request = Request::create("/voice/{$sessionId}/close", 'POST', $body);

    return $controller($request, $sessionId);
}

// ─── Tests ───────────────────────────────────────────────────────

it('marks voice call completed with turns from request', function () {
    $call = createCloseTestVoiceCall('sess-close-turns');
    $execution = createCloseTestExecution('sess-close-turns', $call->id);

    $turns = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ];

    invokeClose('sess-close-turns', ['turns' => $turns]);

    $call->refresh();

    expect($call->status)->toBe(VoiceCallStatus::Completed);
    expect($call->completed_at)->not->toBeNull();
    expect($call->transcript)->toHaveCount(2);
    expect($call->transcript[0]['content'])->toBe('Hello');
    expect($call->transcript[1]['content'])->toBe('Hi there!');
});

it('falls back to stored transcript when no turns provided', function () {
    $storedTranscript = [
        ['role' => 'user', 'content' => 'Stored message'],
        ['role' => 'assistant', 'content' => 'Stored reply'],
        ['role' => 'user', 'content' => 'Another message'],
    ];

    $call = createCloseTestVoiceCall('sess-close-fallback', [
        'transcript' => $storedTranscript,
    ]);
    createCloseTestExecution('sess-close-fallback', $call->id);

    invokeClose('sess-close-fallback');

    $call->refresh();

    expect($call->status)->toBe(VoiceCallStatus::Completed);
    expect($call->transcript)->toHaveCount(3);
    expect($call->transcript[0]['content'])->toBe('Stored message');
});

it('completes linked execution record', function () {
    $call = createCloseTestVoiceCall('sess-close-exec');
    $execution = createCloseTestExecution('sess-close-exec', $call->id);

    invokeClose('sess-close-exec');

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect($execution->completed_at)->not->toBeNull();
});

it('fires VoiceCallCompleted event with correct data', function () {
    Event::fake([VoiceCallCompleted::class]);

    $call = createCloseTestVoiceCall('sess-close-event-vc');
    createCloseTestExecution('sess-close-event-vc', $call->id);

    $turns = [
        ['role' => 'user', 'content' => 'Test turn'],
    ];

    invokeClose('sess-close-event-vc', ['turns' => $turns]);

    Event::assertDispatched(VoiceCallCompleted::class, function (VoiceCallCompleted $event) use ($call) {
        return $event->voiceCallId === $call->id
            && $event->sessionId === 'sess-close-event-vc'
            && count($event->transcript) === 1
            && $event->durationMs !== null;
    });
});

it('fires VoiceSessionClosed event', function () {
    Event::fake([VoiceSessionClosed::class]);

    $call = createCloseTestVoiceCall('sess-close-event-sc');

    invokeClose('sess-close-event-sc');

    Event::assertDispatched(VoiceSessionClosed::class, function (VoiceSessionClosed $event) {
        return $event->sessionId === 'sess-close-event-sc'
            && $event->provider === 'xai';
    });
});

it('clears session cache on close', function () {
    $call = createCloseTestVoiceCall('sess-close-cache');

    Cache::put('voice:sess-close-cache:tools', [
        'tools' => ['some_tool' => 'SomeClass'],
        'user_id' => 1,
        'execution_id' => 99,
    ], 3600);

    expect(Cache::has('voice:sess-close-cache:tools'))->toBeTrue();

    invokeClose('sess-close-cache');

    expect(Cache::has('voice:sess-close-cache:tools'))->toBeFalse();
});

it('returns 204 on success', function () {
    $call = createCloseTestVoiceCall('sess-close-204');
    createCloseTestExecution('sess-close-204', $call->id);

    $response = invokeClose('sess-close-204', [
        'turns' => [['role' => 'user', 'content' => 'Bye']],
    ]);

    expect($response->getStatusCode())->toBe(204);
});

it('handles close when no voice call exists', function () {
    $response = invokeClose('nonexistent-session-id');

    expect($response->getStatusCode())->toBe(204);
});

it('is idempotent — double close does not error', function () {
    $call = createCloseTestVoiceCall('sess-close-idempotent');
    $execution = createCloseTestExecution('sess-close-idempotent', $call->id);

    $first = invokeClose('sess-close-idempotent', [
        'turns' => [['role' => 'user', 'content' => 'Hello']],
    ]);
    $second = invokeClose('sess-close-idempotent');

    expect($first->getStatusCode())->toBe(204);
    expect($second->getStatusCode())->toBe(204);

    $call->refresh();
    expect($call->status)->toBe(VoiceCallStatus::Completed);
});
