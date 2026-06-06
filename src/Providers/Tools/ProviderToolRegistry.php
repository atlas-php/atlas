<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Tools;

/**
 * Which provider-native tools each provider can execute.
 *
 * This is the single source of truth a consuming application can query to build
 * provider-aware UI and validation without hardcoding (or drifting from) the
 * support matrix. Every entry below is verified against the live provider API.
 *
 * Note: support is by tool *type*. Some tools still need their own attributes to
 * run (e.g. OpenAI `file_search` needs `vector_store_ids`); the tool classes carry
 * those, while this registry only answers "can provider X run tool type Y?".
 */
class ProviderToolRegistry
{
    /**
     * @var array<string, array<int, string>>
     */
    private const SUPPORT = [
        'openai' => ['web_search', 'file_search', 'code_interpreter'],
        'anthropic' => ['web_search', 'web_fetch'],
        'google' => ['google_search', 'code_execution'],
        'xai' => ['web_search', 'x_search'],
    ];

    /**
     * The full provider → supported tool-type map.
     *
     * @return array<string, array<int, string>>
     */
    public static function all(): array
    {
        return self::SUPPORT;
    }

    /**
     * Tool types the given provider can execute.
     *
     * @return array<int, string>
     */
    public static function forProvider(string $provider): array
    {
        return self::SUPPORT[$provider] ?? [];
    }

    /**
     * Whether the provider can execute the given provider-tool type.
     */
    public static function supports(string $provider, string $type): bool
    {
        return in_array($type, self::forProvider($provider), true);
    }
}
