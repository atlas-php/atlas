<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\CalculatorTool;
use App\Tools\DateTimeTool;
use App\Tools\WeatherTool;
use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Gemini agent with tool support for testing.
 */
class GeminiToolDemoAgent extends AgentDefinition
{
    public function provider(): ?string
    {
        return 'gemini';
    }

    public function model(): ?string
    {
        return 'gemini-2.0-flash';
    }

    public function systemPrompt(): ?string
    {
        return 'You are a helpful assistant with access to tools. '
            .'Use the calculator for math, weather for weather queries, '
            .'and datetime for time queries. Always use tools when appropriate.';
    }

    /**
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

    public function maxSteps(): ?int
    {
        return 5;
    }
}
