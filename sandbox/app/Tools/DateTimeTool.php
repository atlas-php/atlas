<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Atlasphp\Atlas\Tools\ToolDefinition;
use DateTimeImmutable;
use DateTimeZone;

/**
 * DateTime tool for current date and time information.
 *
 * Returns current date/time in various formats and timezones.
 */
class DateTimeTool extends ToolDefinition
{
    /**
     * Get the tool name.
     */
    public function name(): string
    {
        return 'datetime';
    }

    /**
     * Get the tool description.
     */
    public function description(): string
    {
        return 'Get the current date and time. Can return in different timezones and formats.';
    }

    /**
     * Get the tool parameters.
     *
     * @return array<int, ToolParameter>
     */
    public function parameters(): array
    {
        return [
            ToolParameter::string(
                'timezone',
                'The timezone (e.g., UTC, America/New_York, Europe/London). Defaults to UTC.',
                required: false,
                default: 'UTC',
            ),
            ToolParameter::enum(
                'format',
                'The output format',
                ['full', 'date', 'time', 'iso8601'],
                required: false,
                default: 'full',
            ),
        ];
    }

    /**
     * Execute the tool.
     *
     * @param  array{timezone?: string, format?: string}  $args
     */
    public function handle(array $args, ToolContext $context): ToolResult
    {
        $timezone = $args['timezone'] ?? 'UTC';
        $format = $args['format'] ?? 'full';

        try {
            $tz = new DateTimeZone($timezone);
        } catch (\Exception) {
            return ToolResult::error("Invalid timezone: {$timezone}");
        }

        $now = new DateTimeImmutable('now', $tz);

        $formatted = match ($format) {
            'date' => $now->format('Y-m-d'),
            'time' => $now->format('H:i:s'),
            'iso8601' => $now->format('c'),
            default => $now->format('l, F j, Y g:i:s A T'),
        };

        $data = [
            'datetime' => $formatted,
            'timezone' => $timezone,
            'format' => $format,
            'timestamp' => $now->getTimestamp(),
            'day_of_week' => $now->format('l'),
            'week_number' => (int) $now->format('W'),
        ];

        return ToolResult::json($data);
    }
}
