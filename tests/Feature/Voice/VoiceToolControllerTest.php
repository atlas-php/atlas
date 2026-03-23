<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Voice\Http\VoiceToolController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// ─── Test Tools ─────────────────────────────────────────────────

class VoiceTestEchoTool extends Tool
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
        return 'Echo: '.($args['message'] ?? 'empty').' sid:'.$context['session_id'];
    }
}

class VoiceTestFailingTool extends Tool
{
    public function name(): string
    {
        return 'failing_tool';
    }

    public function description(): string
    {
        return 'Always throws.';
    }

    public function handle(array $args, array $context): string
    {
        throw new RuntimeException('Internal error details');
    }
}

class VoiceTestArrayTool extends Tool
{
    public function name(): string
    {
        return 'array_tool';
    }

    public function description(): string
    {
        return 'Returns array.';
    }

    public function handle(array $args, array $context): array
    {
        return ['status' => 'ok', 'value' => 42];
    }
}

// ─── Helper ─────────────────────────────────────────────────────

function invokeToolController(string $sessionId, array $body): \Illuminate\Http\JsonResponse
{
    $controller = app(VoiceToolController::class);
    $request = Request::create("/voice/{$sessionId}/tool", 'POST', $body);

    return $controller($request, $sessionId);
}

function seedToolSession(string $sessionId, array $toolMap): void
{
    Cache::put("voice:{$sessionId}:tools", [
        'tools' => $toolMap,
        'user_id' => 1,
    ], 3600);
}

// ─── Tests ──────────────────────────────────────────────────────

it('executes a registered tool and returns the result', function () {
    seedToolSession('s1', ['echo_test' => VoiceTestEchoTool::class]);

    $response = invokeToolController('s1', [
        'name' => 'echo_test',
        'arguments' => json_encode(['message' => 'hello']),
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true)['output'])->toBe('Echo: hello sid:s1');
});

it('returns 404 when session is not found', function () {
    $response = invokeToolController('nonexistent', [
        'name' => 'echo_test',
        'arguments' => '{}',
    ]);

    expect($response->getStatusCode())->toBe(404);
    $output = json_decode($response->getData(true)['output'], true);
    expect($output['error'])->toContain('Session not found');
});

it('returns 404 when tool name is not registered', function () {
    seedToolSession('s2', ['echo_test' => VoiceTestEchoTool::class]);

    $response = invokeToolController('s2', [
        'name' => 'unknown_tool',
        'arguments' => '{}',
    ]);

    expect($response->getStatusCode())->toBe(404);
    $output = json_decode($response->getData(true)['output'], true);
    expect($output['error'])->toContain('Unknown tool');
});

it('handles tool failure gracefully', function () {
    seedToolSession('s3', ['failing_tool' => VoiceTestFailingTool::class]);

    $response = invokeToolController('s3', [
        'name' => 'failing_tool',
        'arguments' => '{}',
    ]);

    expect($response->getStatusCode())->toBe(200);
    $output = json_decode($response->getData(true)['output'], true);
    expect($output['error'])->toBeString();
});

it('hides exception details in production mode', function () {
    config(['app.debug' => false]);
    seedToolSession('s4', ['failing_tool' => VoiceTestFailingTool::class]);

    $response = invokeToolController('s4', [
        'name' => 'failing_tool',
        'arguments' => '{}',
    ]);

    $output = json_decode($response->getData(true)['output'], true);
    expect($output['error'])->toBe('Tool execution failed');
});

it('exposes exception details in debug mode', function () {
    config(['app.debug' => true]);
    seedToolSession('s5', ['failing_tool' => VoiceTestFailingTool::class]);

    $response = invokeToolController('s5', [
        'name' => 'failing_tool',
        'arguments' => '{}',
    ]);

    $output = json_decode($response->getData(true)['output'], true);
    expect($output['error'])->toBe('Internal error details');
});

it('serializes array results from tools', function () {
    seedToolSession('s6', ['array_tool' => VoiceTestArrayTool::class]);

    $response = invokeToolController('s6', [
        'name' => 'array_tool',
        'arguments' => '{}',
    ]);

    expect($response->getStatusCode())->toBe(200);
    $output = json_decode($response->getData(true)['output'], true);
    expect($output['status'])->toBe('ok');
    expect($output['value'])->toBe(42);
});

it('passes session_id in tool context', function () {
    seedToolSession('ctx-session-42', ['echo_test' => VoiceTestEchoTool::class]);

    $response = invokeToolController('ctx-session-42', [
        'name' => 'echo_test',
        'arguments' => json_encode(['message' => 'test']),
    ]);

    expect($response->getData(true)['output'])->toContain('sid:ctx-session-42');
});
