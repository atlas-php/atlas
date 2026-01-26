<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Atlasphp\Atlas\Tools\ToolDefinition;

/**
 * Mock weather tool for testing.
 *
 * Returns simulated weather data for any location.
 */
class WeatherTool extends ToolDefinition
{
    /**
     * Get the tool name.
     */
    public function name(): string
    {
        return 'weather';
    }

    /**
     * Get the tool description.
     */
    public function description(): string
    {
        return 'Get the current weather for a location. Returns temperature, conditions, and humidity.';
    }

    /**
     * Get the tool parameters.
     *
     * @return array<int, \Prism\Prism\Contracts\Schema>
     */
    public function parameters(): array
    {
        return [
            ToolParameter::string(
                'location',
                'The city name or location to get weather for',
            ),
            ToolParameter::enum(
                'units',
                'Temperature units (default: celsius)',
                ['celsius', 'fahrenheit'],
            ),
        ];
    }

    /**
     * Execute the tool.
     *
     * @param  array{location: string, units?: string}  $params
     */
    public function handle(array $params, ToolContext $context): ToolResult
    {
        $location = $params['location'] ?? 'Unknown';
        $units = $params['units'] ?? 'celsius';

        // Generate deterministic "random" weather based on location name
        $hash = crc32(strtolower($location));
        $tempBase = ($hash % 30) + 5; // 5-35 base temperature
        $conditions = ['sunny', 'cloudy', 'partly cloudy', 'rainy', 'overcast'];
        $condition = $conditions[$hash % count($conditions)];
        $humidity = 30 + ($hash % 50); // 30-80%

        // Convert to fahrenheit if requested
        $temp = $units === 'fahrenheit'
            ? round($tempBase * 9 / 5 + 32)
            : $tempBase;
        $unitSymbol = $units === 'fahrenheit' ? 'F' : 'C';

        $data = [
            'location' => $location,
            'temperature' => $temp,
            'units' => $unitSymbol,
            'conditions' => $condition,
            'humidity' => $humidity,
            'note' => 'This is simulated weather data for testing purposes.',
        ];

        return ToolResult::json($data);
    }
}
