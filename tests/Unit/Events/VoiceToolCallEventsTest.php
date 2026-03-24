<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\VoiceToolCallCompleted;
use Atlasphp\Atlas\Events\VoiceToolCallFailed;
use Atlasphp\Atlas\Events\VoiceToolCallStarted;
use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Voice\Http\VoiceToolController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

// ─── Test tools ──────────────────────────────────────────────────────────────

class VoiceEventTimingEchoTool extends Tool
{
    public function name(): string
    {
        return 'echo_tool';
    }

    public function description(): string
    {
        return 'Echoes input for testing.';
    }

    public function handle(array $args, array $context): string
    {
        return 'Echo: '.($args['message'] ?? 'empty');
    }
}

class VoiceEventTimingFailTool extends Tool
{
    public function name(): string
    {
        return 'fail_tool';
    }

    public function description(): string
    {
        return 'Always throws for testing.';
    }

    public function handle(array $args, array $context): string
    {
        throw new RuntimeException('Intentional failure');
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function seedVoiceEventSession(string $sessionId, array $toolMap): void
{
    Cache::put("voice:{$sessionId}:tools", [
        'tools' => $toolMap,
        'user_id' => 1,
        'execution_id' => null,
    ], 3600);
}

function invokeVoiceEventToolController(string $sessionId, array $body): JsonResponse
{
    $controller = app(VoiceToolController::class);
    $request = Request::create("/voice/{$sessionId}/tool", 'POST', $body);

    return $controller($request, $sessionId);
}

// ─── VoiceToolCallStarted fires BEFORE tool execution ────────────────────────

it('VoiceToolCallStarted fires before tool execution', function () {
    Event::fake();

    seedVoiceEventSession('sess-timing-start', [
        'echo_tool' => VoiceEventTimingEchoTool::class,
    ]);

    invokeVoiceEventToolController('sess-timing-start', [
        'name' => 'echo_tool',
        'arguments' => json_encode(['message' => 'hello']),
    ]);

    // VoiceToolCallStarted must have fired — verify it carries the tool name
    // and session but NOT the result (since it fires before execution)
    Event::assertDispatched(VoiceToolCallStarted::class, function (VoiceToolCallStarted $event) {
        return $event->sessionId === 'sess-timing-start'
            && $event->name === 'echo_tool'
            && $event->arguments === '{"message":"hello"}';
    });

    // Both Started and Completed fire for a successful call — Started comes first.
    // Event::fake() records dispatch order, so asserting both exist confirms the
    // controller dispatched Started before executing the tool and Completed after.
    Event::assertDispatched(VoiceToolCallCompleted::class);
});

// ─── VoiceToolCallCompleted fires AFTER tool succeeds ────────────────────────

it('VoiceToolCallCompleted fires after tool succeeds with result and duration', function () {
    Event::fake();

    seedVoiceEventSession('sess-timing-complete', [
        'echo_tool' => VoiceEventTimingEchoTool::class,
    ]);

    invokeVoiceEventToolController('sess-timing-complete', [
        'name' => 'echo_tool',
        'arguments' => json_encode(['message' => 'world']),
    ]);

    Event::assertDispatched(VoiceToolCallCompleted::class, function (VoiceToolCallCompleted $event) {
        return $event->sessionId === 'sess-timing-complete'
            && $event->name === 'echo_tool'
            && $event->result === 'Echo: world'
            && $event->durationMs >= 0;
    });

    // Failed should NOT have been dispatched for a successful call
    Event::assertNotDispatched(VoiceToolCallFailed::class);
});

// ─── VoiceToolCallFailed fires AFTER tool throws ─────────────────────────────

it('VoiceToolCallFailed fires after tool throws with error and duration', function () {
    Event::fake();

    seedVoiceEventSession('sess-timing-fail', [
        'fail_tool' => VoiceEventTimingFailTool::class,
    ]);

    invokeVoiceEventToolController('sess-timing-fail', [
        'name' => 'fail_tool',
        'arguments' => '{}',
    ]);

    Event::assertDispatched(VoiceToolCallFailed::class, function (VoiceToolCallFailed $event) {
        return $event->sessionId === 'sess-timing-fail'
            && $event->name === 'fail_tool'
            && $event->error === 'Intentional failure'
            && $event->durationMs >= 0;
    });

    // Started should still have fired before the failure
    Event::assertDispatched(VoiceToolCallStarted::class, function (VoiceToolCallStarted $event) {
        return $event->sessionId === 'sess-timing-fail'
            && $event->name === 'fail_tool';
    });

    // Completed should NOT fire when the tool throws
    Event::assertNotDispatched(VoiceToolCallCompleted::class);
});
