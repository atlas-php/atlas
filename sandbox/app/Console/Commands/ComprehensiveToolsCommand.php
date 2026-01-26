<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use Illuminate\Console\Command;

/**
 * Command for comprehensive tool verification testing.
 *
 * Tests that all expected tools are called and verifies tool execution
 * with detailed output showing tool calls, arguments, and results.
 */
class ComprehensiveToolsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:comprehensive-tools
                            {--with-mcp : Include MCP tools if available}
                            {--verify-all : Fail if any expected tool was not called}
                            {--agent=comprehensive-tool : Agent to use for testing}
                            {--prompt= : Custom prompt (default uses tool demonstration request)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test comprehensive tool execution with verification';

    /**
     * Expected tools for verification.
     *
     * @var array<string>
     */
    protected array $expectedTools = ['calculator', 'weather', 'datetime'];

    /**
     * Execute the console command.
     */
    public function handle(
        AgentRegistryContract $agentRegistry,
        ToolRegistryContract $toolRegistry,
    ): int {
        $agentKey = $this->option('agent');
        $withMcp = $this->option('with-mcp');
        $verifyAll = $this->option('verify-all');
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
        $toolNames = $this->getToolNames($tools, $toolRegistry);

        $this->displayHeader($agentKey, $toolNames, $withMcp);

        // Use default prompt if not provided
        if (! $prompt) {
            $prompt = 'Please demonstrate ALL your available tools. '
                .'Calculate 42 * 17, get the weather for Paris, and tell me the current date and time.';
        }

        try {
            $response = $this->executeAgent($agentKey, $prompt, $withMcp);
            $this->displayVerificationOutput($response, $verifyAll);

            return $verifyAll ? $this->getVerificationExitCode($response) : self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Get tool names from tool classes.
     *
     * Tool classes are instantiated via the container to get their names.
     *
     * @param  array<int, class-string>  $tools
     * @return array<string>
     */
    protected function getToolNames(array $tools, ToolRegistryContract $toolRegistry): array
    {
        return array_map(
            fn (string $toolClass): string => app($toolClass)->name(),
            $tools
        );
    }

    /**
     * Display the command header.
     *
     * @param  array<string>  $toolNames
     */
    protected function displayHeader(string $agentKey, array $toolNames, bool $withMcp): void
    {
        $this->line('');
        $this->line('=== Atlas Comprehensive Tools Test ===');
        $this->line("Agent: {$agentKey}");
        $this->line('Available Tools: '.implode(', ', $toolNames));

        if ($withMcp) {
            $this->line('MCP Tools: Enabled (if configured)');
        }

        $this->line('');
    }

    /**
     * Execute the agent with the given prompt.
     */
    protected function executeAgent(string $agentKey, string $prompt, bool $withMcp): AgentResponse
    {
        $this->info("Prompt: \"{$prompt}\"");
        $this->line('');

        $agentRequest = Atlas::agent($agentKey);

        // Note: MCP tools would be added here if Relay is configured
        // For now, we skip MCP since Relay may not be available

        return $agentRequest->chat($prompt);
    }

    /**
     * Display the verification output.
     */
    protected function displayVerificationOutput(AgentResponse $response, bool $verifyAll): void
    {
        $this->line('=== Tool Execution Verification ===');
        $this->line('');

        // Get called tools
        $calledTools = $this->extractCalledTools($response);

        $this->line('Expected Tools: '.implode(', ', $this->expectedTools));
        $this->line('Called Tools: '.(empty($calledTools) ? '(none)' : implode(', ', array_unique($calledTools))));
        $this->line('');

        // Display tool call details
        $this->displayToolCallDetails($response);

        // Display verification results
        $this->displayVerificationResults($response, $verifyAll);

        // Display final response
        $this->displayFinalResponse($response);

        // Display token usage
        $this->displayTokenUsage($response);
    }

    /**
     * Extract called tool names from the response.
     *
     * Tool calls are found in the response steps, not on the final response.
     *
     * @return array<string>
     */
    protected function extractCalledTools(AgentResponse $response): array
    {
        $calledTools = [];

        // Tool calls are in the steps (multi-step agentic loop)
        foreach ($response->steps() as $step) {
            if (property_exists($step, 'toolCalls') && is_array($step->toolCalls)) {
                foreach ($step->toolCalls as $toolCall) {
                    $calledTools[] = $toolCall->name;
                }
            }
        }

        return $calledTools;
    }

    /**
     * Display detailed tool call information.
     */
    protected function displayToolCallDetails(AgentResponse $response): void
    {
        $this->line('--- Tool Call Details ---');

        $stepNumber = 0;

        // Process steps for detailed tool calls
        foreach ($response->steps() as $step) {
            if (! property_exists($step, 'toolCalls') || ! is_array($step->toolCalls)) {
                continue;
            }

            foreach ($step->toolCalls as $toolCall) {
                $stepNumber++;
                $this->line("Step {$stepNumber}: {$toolCall->name}");
                $this->line('  Args: '.json_encode(json_decode($toolCall->arguments, true), JSON_UNESCAPED_SLASHES));

                // Find the result for this tool call in the same step
                $result = $this->findToolResultInStep($step, $toolCall->id);
                if ($result !== null) {
                    $resultStr = is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_SLASHES);
                    // Truncate long results
                    if (strlen($resultStr) > 200) {
                        $resultStr = substr($resultStr, 0, 200).'...';
                    }
                    $this->line("  Result: {$resultStr}");
                }

                $this->line('');
            }
        }

        if ($stepNumber === 0) {
            $this->warn('No tool calls detected.');
            $this->line('');
        }
    }

    /**
     * Find the result for a tool call within a step.
     */
    protected function findToolResultInStep(mixed $step, string $toolCallId): mixed
    {
        if (! property_exists($step, 'toolResults') || ! is_array($step->toolResults)) {
            return null;
        }

        foreach ($step->toolResults as $result) {
            if ($result->toolCallId === $toolCallId) {
                return $result->result;
            }
        }

        return null;
    }

    /**
     * Display verification results.
     */
    protected function displayVerificationResults(AgentResponse $response, bool $verifyAll): void
    {
        $calledTools = array_unique($this->extractCalledTools($response));
        $missingTools = array_diff($this->expectedTools, $calledTools);
        $allToolsCalled = empty($missingTools);

        $this->line('--- Verification Results ---');

        // Check expected tools
        $expectedCount = count($this->expectedTools);
        $calledCount = count(array_intersect($this->expectedTools, $calledTools));

        if ($allToolsCalled) {
            $this->info("[PASS] All {$expectedCount} expected tools were called");
        } else {
            $method = $verifyAll ? 'error' : 'warn';
            $this->{$method}("[FAIL] Only {$calledCount}/{$expectedCount} expected tools were called");
            $this->{$method}('Missing: '.implode(', ', $missingTools));
        }

        // Check if response incorporated tool results
        $text = $response->text();
        if ($text !== '') {
            $this->info('[PASS] Agent provided text response');
        } else {
            $this->warn('[WARN] Agent did not provide text response');
        }

        $this->line('');
    }

    /**
     * Display the final response text.
     */
    protected function displayFinalResponse(AgentResponse $response): void
    {
        $this->line('--- Final Response ---');
        $text = $response->text() ?: '[No text response]';
        $this->line($text);
        $this->line('');
    }

    /**
     * Display token usage.
     */
    protected function displayTokenUsage(AgentResponse $response): void
    {
        $usage = $response->usage();
        $this->line('--- Token Usage ---');
        $this->line(sprintf(
            'Prompt: %d | Completion: %d | Total: %d',
            $usage->promptTokens,
            $usage->completionTokens,
            $usage->promptTokens + $usage->completionTokens,
        ));
        $this->line('');
    }

    /**
     * Get the exit code based on verification results.
     */
    protected function getVerificationExitCode(AgentResponse $response): int
    {
        $calledTools = array_unique($this->extractCalledTools($response));
        $missingTools = array_diff($this->expectedTools, $calledTools);

        return empty($missingTools) ? self::SUCCESS : self::FAILURE;
    }
}
