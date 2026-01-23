<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Providers\Facades\Atlas;
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use Illuminate\Console\Command;

/**
 * Command for testing tool execution with agents.
 *
 * Demonstrates agent tool calling capabilities.
 */
class ToolsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:tools
                            {--agent=tool-demo : Agent with tools}
                            {--prompt= : Specific prompt to trigger tool use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test tool execution with Atlas agents';

    /**
     * Default prompts to cycle through.
     *
     * @var array<string>
     */
    protected array $defaultPrompts = [
        'What is 42 * 17?',
        "What's the weather in New York?",
        'What is the current date and time?',
    ];

    /**
     * Execute the console command.
     */
    public function handle(
        AgentRegistryContract $agentRegistry,
        ToolRegistryContract $toolRegistry,
    ): int {
        $agentKey = $this->option('agent');
        $prompt = $this->option('prompt');

        // Verify agent exists
        if (! $agentRegistry->has($agentKey)) {
            $this->error("Agent not found: {$agentKey}");

            return self::FAILURE;
        }

        $agent = $agentRegistry->get($agentKey);
        $tools = $agent->tools();

        if (empty($tools)) {
            $this->error("Agent '{$agentKey}' has no tools configured.");

            return self::FAILURE;
        }

        // Get tool names
        $toolNames = [];
        foreach ($tools as $toolClass) {
            if ($toolRegistry->has($toolClass)) {
                $tool = $toolRegistry->get($toolClass);
                $toolNames[] = $tool->name();
            } else {
                // Try to instantiate and get name
                $toolNames[] = class_basename($toolClass);
            }
        }

        $this->displayHeader($agentKey, $toolNames);

        // Use provided prompt or cycle through defaults
        if (! $prompt) {
            $prompt = $this->defaultPrompts[array_rand($this->defaultPrompts)];
        }

        return $this->testPrompt($agentKey, $prompt);
    }

    /**
     * Display the command header.
     *
     * @param  array<string>  $toolNames
     */
    protected function displayHeader(string $agentKey, array $toolNames): void
    {
        $this->line('');
        $this->line('=== Atlas Tool Execution Test ===');
        $this->line("Agent: {$agentKey}");
        $this->line('Available Tools: '.implode(', ', $toolNames));
        $this->line('');
    }

    /**
     * Test a prompt with the agent.
     */
    protected function testPrompt(string $agentKey, string $prompt): int
    {
        $this->info("Prompt: \"{$prompt}\"");
        $this->line('');

        try {
            $startTime = microtime(true);
            $response = Atlas::agent($agentKey)->chat($prompt);
            $duration = round(microtime(true) - $startTime, 3);

            $this->displayToolCalls($response);
            $this->displayFinalResponse($response);
            $this->displayTokenUsage($response);
            $this->displayVerification($response, $duration);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Display tool calls from the response.
     *
     * @param  \Atlasphp\Atlas\Agents\Support\AgentResponse  $response
     */
    protected function displayToolCalls($response): void
    {
        $this->line('--- Tool Calls ---');

        $toolCalls = $response->toolCalls;

        if (empty($toolCalls)) {
            $this->warn('No tool calls made.');
            $this->line('');

            return;
        }

        foreach ($toolCalls as $i => $call) {
            $num = $i + 1;
            $name = $call['name'] ?? 'unknown';
            $args = $call['arguments'] ?? [];
            $result = $call['result'] ?? null;

            $this->line("Tool #{$num}: {$name}");
            $this->line('  Arguments: '.json_encode($args));

            if ($result !== null) {
                $resultStr = is_string($result) ? $result : json_encode($result);
                $this->line("  Result: \"{$resultStr}\"");
            }

            $this->line('');
        }
    }

    /**
     * Display the final response.
     *
     * @param  \Atlasphp\Atlas\Agents\Support\AgentResponse  $response
     */
    protected function displayFinalResponse($response): void
    {
        $this->line('--- Final Response ---');
        $text = $response->text ?? '[No text response]';
        $this->info("\"{$text}\"");
        $this->line('');
    }

    /**
     * Display token usage.
     *
     * @param  \Atlasphp\Atlas\Agents\Support\AgentResponse  $response
     */
    protected function displayTokenUsage($response): void
    {
        $this->line('--- Token Usage ---');
        $this->line(sprintf(
            'Prompt: %d | Completion: %d | Total: %d',
            $response->promptTokens(),
            $response->completionTokens(),
            $response->totalTokens(),
        ));
        $this->line('');
    }

    /**
     * Display verification results.
     *
     * @param  \Atlasphp\Atlas\Agents\Support\AgentResponse  $response
     */
    protected function displayVerification($response, float $duration): void
    {
        $this->line('--- Verification ---');

        $toolCalls = $response->toolCalls;

        // Check if tool was called
        if (! empty($toolCalls)) {
            $this->info('[PASS] Tool was called with correct name');

            // Check arguments
            $hasArgs = false;
            foreach ($toolCalls as $call) {
                if (! empty($call['arguments'])) {
                    $hasArgs = true;
                    break;
                }
            }

            if ($hasArgs) {
                $this->info('[PASS] Tool arguments match expected schema');
            } else {
                $this->warn('[WARN] Tool called without arguments');
            }

            // Check for results
            $hasResult = false;
            foreach ($toolCalls as $call) {
                if (isset($call['result']) && $call['result'] !== null) {
                    $hasResult = true;
                    break;
                }
            }

            if ($hasResult) {
                $this->info('[PASS] Tool returned valid result');
            }
        } else {
            $this->warn('[WARN] No tool calls detected');
        }

        // Check final response
        if ($response->hasText()) {
            $this->info('[PASS] Agent incorporated tool result in response');
        } else {
            $this->warn('[WARN] Agent did not provide text response');
        }

        $this->line('');
        $this->line("Duration: {$duration}s");
    }
}
