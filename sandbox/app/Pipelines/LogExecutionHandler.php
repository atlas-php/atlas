<?php

declare(strict_types=1);

namespace App\Pipelines;

use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Pipeline handler that logs execution details.
 *
 * Demonstrates how to observe agent execution via the after_execute
 * pipeline for logging, metrics, or auditing purposes.
 */
class LogExecutionHandler implements PipelineContract
{
    /**
     * Captured log entries for testing/display.
     *
     * @var array<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    protected static array $capturedLogs = [];

    /**
     * Whether to capture logs instead of writing to Laravel's log.
     */
    protected static bool $captureMode = false;

    /**
     * Enable capture mode for testing.
     */
    public static function enableCapture(): void
    {
        self::$captureMode = true;
        self::$capturedLogs = [];
    }

    /**
     * Disable capture mode.
     */
    public static function disableCapture(): void
    {
        self::$captureMode = false;
    }

    /**
     * Get captured logs.
     *
     * @return array<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    public static function getCapturedLogs(): array
    {
        return self::$capturedLogs;
    }

    /**
     * Clear captured logs.
     */
    public static function clearCapturedLogs(): void
    {
        self::$capturedLogs = [];
    }

    /**
     * Handle the pipeline data.
     *
     * @param  array{agent: mixed, input: string, context: mixed, response: mixed, system_prompt: string|null}  $data
     */
    public function handle(mixed $data, Closure $next): mixed
    {
        $agent = $data['agent'];
        $input = $data['input'];
        $response = $data['response'];
        $context = $data['context'];

        // Count tool calls from steps
        $toolCallsCount = 0;
        $toolNames = [];
        foreach ($response->steps as $step) {
            if (property_exists($step, 'toolCalls') && is_array($step->toolCalls)) {
                $toolCallsCount += count($step->toolCalls);
                foreach ($step->toolCalls as $call) {
                    $toolNames[] = $call->name;
                }
            }
        }

        // Build log context
        $logContext = [
            'agent_key' => $agent->key(),
            'agent_name' => $agent->name(),
            'input_length' => strlen($input),
            'prompt_tokens' => $response->usage->promptTokens ?? 0,
            'completion_tokens' => $response->usage->completionTokens ?? 0,
            'total_tokens' => ($response->usage->promptTokens ?? 0) + ($response->usage->completionTokens ?? 0),
            'finish_reason' => $response->finishReason->value ?? 'unknown',
            'tool_calls_count' => $toolCallsCount,
            'tools_called' => array_unique($toolNames),
            'has_metadata' => ! empty($context->metadata),
            'metadata_keys' => array_keys($context->metadata),
        ];

        // Log to appropriate destination
        $this->log('info', 'Agent execution completed', $logContext);

        return $next($data);
    }

    /**
     * Log a message.
     *
     * @param  array<string, mixed>  $context
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (self::$captureMode) {
            self::$capturedLogs[] = [
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ];
        } else {
            Log::log($level, $message, $context);
        }
    }
}
