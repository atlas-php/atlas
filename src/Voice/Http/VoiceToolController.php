<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Voice\Http;

use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Tools\ToolSerializer;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Executes Atlas Tool classes for voice sessions.
 *
 * The browser relays tool calls from the provider to this endpoint.
 * Tool class names are stored in cache when the session is created.
 * The controller resolves the class from the container and calls handle().
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
        ]);

        $name = $validated['name'];
        $args = json_decode($validated['arguments'], true) ?? [];

        // Verify the session exists and load registered tools
        /** @var array<string, mixed>|null $sessionData */
        $sessionData = Cache::get("voice:{$sessionId}:tools");

        if ($sessionData === null) {
            return response()->json([
                'output' => json_encode(['error' => 'Session not found or expired']),
            ], 404);
        }

        /** @var array<string, class-string<Tool>> $toolMap */
        $toolMap = (array) ($sessionData['tools'] ?? []);

        $toolClass = $toolMap[$name] ?? null;

        if ($toolClass === null) {
            return response()->json([
                'output' => json_encode(['error' => "Unknown tool: {$name}"]),
            ]);
        }

        try {
            /** @var Tool $tool */
            $tool = $this->container->make($toolClass);

            $result = $tool->handle($args, [
                'session_id' => $sessionId,
            ]);

            return response()->json([
                'output' => ToolSerializer::serialize($result),
            ]);
        } catch (\Throwable $e) {
            logger()->error('[VoiceToolController] Tool execution failed: '.$e->getMessage(), [
                'tool' => $name,
                'session_id' => $sessionId,
                'exception' => $e,
            ]);

            $errorMessage = config('app.debug') ? $e->getMessage() : 'Tool execution failed';

            return response()->json([
                'output' => json_encode(['error' => $errorMessage]),
            ]);
        }
    }
}
