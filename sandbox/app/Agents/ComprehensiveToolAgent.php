<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\CalculatorTool;
use App\Tools\DateTimeTool;
use App\Tools\WeatherTool;
use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Agent with comprehensive tools for testing tool execution verification.
 *
 * This agent has a strict system prompt requiring ALL tools to be used
 * when asked to demonstrate capabilities. Used for tool verification testing.
 */
class ComprehensiveToolAgent extends AgentDefinition
{
    /**
     * Get the AI provider for this agent.
     */
    public function provider(): ?string
    {
        return 'openai';
    }

    /**
     * Get the model to use for this agent.
     */
    public function model(): ?string
    {
        return 'gpt-4o';
    }

    /**
     * Get the system prompt for this agent.
     *
     * The prompt is VERY strict about requiring ALL tools to be used.
     */
    public function systemPrompt(): ?string
    {
        return <<<'PROMPT'
You are a tool demonstration assistant. Your PRIMARY FUNCTION is to execute ALL available tools.

## MANDATORY REQUIREMENTS

When a user asks you to demonstrate tools, use tools, or asks anything, you MUST:

1. **CALCULATOR** - Call the calculator tool FIRST
   - Perform: 42 * 17 (or the calculation the user specifies)
   - This tool is REQUIRED for every response

2. **WEATHER** - Call the weather tool SECOND
   - Get weather for: Paris (or the location the user specifies)
   - This tool is REQUIRED for every response

3. **DATETIME** - Call the datetime tool THIRD
   - Get the current date/time in UTC
   - This tool is REQUIRED for every response

## CRITICAL RULES

- You MUST call ALL THREE tools. No exceptions.
- Call them in order: calculator -> weather -> datetime
- Do NOT skip any tool. Do NOT substitute tools.
- Do NOT just describe what the tools do - actually CALL them.
- After calling all 3 tools, summarize the results.

## VERIFICATION

Before completing your response, verify:
- [ ] Calculator tool was called with actual numbers
- [ ] Weather tool was called with an actual location
- [ ] Datetime tool was called

If ANY tool was not called, you have FAILED the task.
PROMPT;
    }

    /**
     * Get a description of this agent.
     */
    public function description(): ?string
    {
        return 'An assistant that demonstrates all available tools when requested.';
    }

    /**
     * Get the tools available to this agent.
     *
     * @return array<int, class-string>
     */
    public function tools(): array
    {
        return [
            CalculatorTool::class,
            WeatherTool::class,
            DateTimeTool::class,
        ];
    }

    /**
     * Get the maximum steps for tool use iterations.
     */
    public function maxSteps(): ?int
    {
        return 10;
    }
}
