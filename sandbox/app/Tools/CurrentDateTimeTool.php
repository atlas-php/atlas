<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Schema\Fields\StringField;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Support\Carbon;

/**
 * Returns the current date and time.
 *
 * A small, always-available capability for text assistants — useful for
 * "what day is it?" style questions and time-aware answers.
 */
class CurrentDateTimeTool extends Tool
{
    public function name(): string
    {
        return 'get_current_datetime';
    }

    public function description(): string
    {
        return 'Get the current date and time. Use when the user asks what day or time it is, '
            .'or when an answer depends on the current date.';
    }

    /**
     * @return array<int, StringField>
     */
    public function parameters(): array
    {
        return [
            (new StringField('timezone', 'IANA timezone, e.g. "America/New_York". Defaults to UTC.'))->optional(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): string
    {
        $tz = is_string($args['timezone'] ?? null) && $args['timezone'] !== '' ? $args['timezone'] : 'UTC';

        try {
            $now = Carbon::now($tz);
        } catch (\Throwable) {
            $now = Carbon::now('UTC');
            $tz = 'UTC';
        }

        return $now->format('l, F j, Y \a\t g:i A').' ('.$tz.')';
    }
}
