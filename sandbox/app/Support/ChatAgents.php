<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Curated roster of chat-selectable agents for the sandbox UI.
 *
 * Holds presentation metadata (display order, icon, kind) for the agent
 * picker. Behavioural metadata (name, description, model) lives on the
 * Agent classes themselves and is read from the AgentRegistry. Kept as a
 * pure support class rather than a config file because Orchestra Testbench
 * boots providers before sandbox config overrides are applied.
 */
class ChatAgents
{
    /**
     * Ordered roster keyed by agent key.
     *
     * @return array<string, array{icon: string, kind: string}>
     */
    public static function roster(): array
    {
        return [
            'atlas' => ['icon' => 'sparkles', 'kind' => 'text'],
            'thinker' => ['icon' => 'brain', 'kind' => 'text'],
            'stepper' => ['icon' => 'footprints', 'kind' => 'text'],
            'sage' => ['icon' => 'brain', 'kind' => 'text'],
            'iris' => ['icon' => 'image', 'kind' => 'image'],
            'reel' => ['icon' => 'clapperboard', 'kind' => 'video'],
        ];
    }

    /**
     * Ordered list of pickable agent keys.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::roster());
    }

    /**
     * The default agent key for a new conversation.
     */
    public static function default(): string
    {
        return 'atlas';
    }

    /**
     * Whether the given key is a pickable chat agent.
     */
    public static function isPickable(string $key): bool
    {
        return array_key_exists($key, self::roster());
    }
}
