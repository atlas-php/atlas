<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Voice\Http;

use Atlasphp\Atlas\Events\VoiceToolCallCompleted;
use Atlasphp\Atlas\Events\VoiceToolCallFailed;
use Atlasphp\Atlas\Events\VoiceToolCallStarted;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Tools\ToolSerializer;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Executes Atlas Tool classes for voice sessions.
 *
 * The browser relays tool calls from the provider to this endpoint.
 * Tool class names are stored in cache when the session is created.
 * The controller resolves the class from the container, calls handle(),
 * and tracks the execution in the database when persistence is enabled.
 */
class VoiceToolController
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function __invoke(Request $request, string $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'arguments' => 'required|string',
            'call_id' => 'sometimes|string',
        ]);

        $name = $validated['name'];
        try {
            $args = json_decode($validated['arguments'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return response()->json([
                'output' => json_encode(['error' => 'Invalid JSON in arguments'], JSON_THROW_ON_ERROR),
            ], 400);
        }
        $callId = $validated['call_id'] ?? Str::uuid()->toString();

        // Verify the session exists and load registered tools
        /** @var array<string, mixed>|null $sessionData */
        $sessionData = Cache::get("voice:{$sessionId}:tools");

        if ($sessionData === null) {
            return response()->json([
                'output' => json_encode(['error' => 'Session not found or expired'], JSON_THROW_ON_ERROR),
            ], 404);
        }

        /** @var array<string, class-string<Tool>> $toolMap */
        $toolMap = (array) ($sessionData['tools'] ?? []);

        $toolClass = $toolMap[$name] ?? null;

        if ($toolClass === null) {
            return response()->json([
                'output' => json_encode(['error' => "Unknown tool: {$name}"], JSON_THROW_ON_ERROR),
            ], 404);
        }

        // Track tool call in persistence when available
        $record = null;

        try {
            $record = $this->createToolCallRecord($sessionData, $callId, $name, $args, $sessionId);
        } catch (\Throwable $e) {
            report($e);
        }

        $startTime = microtime(true);

        event(new VoiceToolCallStarted(
            sessionId: $sessionId,
            callId: $callId,
            name: $name,
            arguments: json_encode($args, JSON_THROW_ON_ERROR),
        ));

        try {
            /** @var Tool $tool */
            $tool = $this->container->make($toolClass);

            $result = $tool->handle($args, [
                'session_id' => $sessionId,
            ]);

            $serialized = ToolSerializer::serialize($result);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($record !== null) {
                $record->markCompleted($serialized, $durationMs);
            }

            event(new VoiceToolCallCompleted(
                sessionId: $sessionId,
                callId: $callId,
                name: $name,
                result: $serialized,
                durationMs: $durationMs,
            ));

            return response()->json([
                'output' => $serialized,
            ]);
        } catch (\Throwable $e) {
            logger()->error('[VoiceToolController] Tool execution failed: '.$e->getMessage(), [
                'tool' => $name,
                'session_id' => $sessionId,
                'exception' => $e,
            ]);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($record !== null) {
                $record->markFailed($e->getMessage(), $durationMs);
            }

            event(new VoiceToolCallFailed(
                sessionId: $sessionId,
                callId: $callId,
                name: $name,
                error: $e->getMessage(),
                durationMs: $durationMs,
            ));

            $errorMessage = config('app.debug') ? $e->getMessage() : 'Tool execution failed';

            return response()->json([
                'output' => json_encode(['error' => $errorMessage], JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /**
     * Create an ExecutionToolCall record for tracking.
     *
     * @param  array<string, mixed>  $sessionData
     * @param  array<string, mixed>  $args
     */
    private function createToolCallRecord(
        array $sessionData,
        string $callId,
        string $name,
        array $args,
        string $sessionId,
    ): ?ExecutionToolCall {
        $executionId = $sessionData['execution_id'] ?? null;

        if ($executionId === null) {
            return null;
        }

        /** @var class-string<ExecutionToolCall> $model */
        $model = config('atlas.persistence.models.execution_tool_call', ExecutionToolCall::class);

        return $model::create([
            'execution_id' => $executionId,
            'tool_call_id' => $callId,
            'name' => $name,
            'type' => ToolCallType::Local,
            'status' => ExecutionStatus::Processing,
            'arguments' => $args,
            'started_at' => now(),
            'metadata' => ['session_id' => $sessionId],
        ]);
    }
}
