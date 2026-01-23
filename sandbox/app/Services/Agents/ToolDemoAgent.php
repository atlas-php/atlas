<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Services\Tools\CalculatorTool;
use App\Services\Tools\DateTimeTool;
use App\Services\Tools\WeatherTool;
use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Agent with tools for testing tool execution.
 *
 * Demonstrates tool calling capabilities with calculator, weather, and datetime tools.
 */
class ToolDemoAgent extends AgentDefinition
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
     */
    public function systemPrompt(): ?string
    {
        return 'You are a helpful assistant with access to tools. '
            .'Use the available tools when appropriate to answer questions. '
            .'For math calculations, use the calculator tool. '
            .'For weather inquiries, use the weather tool. '
            .'For date and time questions, use the datetime tool.';
    }

    /**
     * Get a description of this agent.
     */
    public function description(): ?string
    {
        return 'An assistant with tools for demonstrating tool execution.';
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
        return 5;
    }
}
