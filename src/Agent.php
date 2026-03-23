<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;

/**
 * Abstract base class for Atlas agents.
 *
 * All methods have sensible defaults — an agent with no overrides is valid.
 * Extend this class and override methods to customize agent behaviour.
 */
abstract class Agent
{
    // ─── Identity ───────────────────────────────────────────────────

    /**
     * Unique key used in Atlas::agent('key').
     * Default: class name in kebab-case, minus "Agent" suffix.
     *   SupportAgent → 'support'
     *   BillingAssistantAgent → 'billing-assistant'
     */
    public function key(): string
    {
        $class = class_basename(static::class);

        // Strip "Agent" suffix
        $name = (string) preg_replace('/Agent$/', '', $class);

        // PascalCase → kebab-case
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
    }

    /**
     * Display name. Default: class name with spaces.
     *   SupportAgent → 'Support'
     *   BillingAssistantAgent → 'Billing Assistant'
     */
    public function name(): string
    {
        $class = class_basename(static::class);
        $name = (string) preg_replace('/Agent$/', '', $class);

        return trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $name));
    }

    /**
     * Description for agent-to-agent delegation.
     */
    public function description(): ?string
    {
        return null;
    }

    // ─── Model ──────────────────────────────────────────────────────

    /**
     * Provider. Null falls back to config/atlas.php default.
     */
    public function provider(): Provider|string|null
    {
        return null;
    }

    /**
     * Model string. Null falls back to config/atlas.php default.
     */
    public function model(): ?string
    {
        return null;
    }

    // ─── Behaviour ──────────────────────────────────────────────────

    /**
     * System message. Supports {variable} interpolation.
     */
    public function instructions(): ?string
    {
        return null;
    }

    /**
     * Atlas tool class names and/or instances.
     * Class strings are resolved via the Laravel container.
     *
     * @return array<int, class-string|object>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * Provider tools (WebSearch, FileSearch, etc.).
     *
     * @return array<int, ProviderTool>
     */
    public function providerTools(): array
    {
        return [];
    }

    // ─── Voice ──────────────────────────────────────────────────────

    /**
     * Provider for voice sessions. Null falls back to provider() then config default.
     */
    public function voiceProvider(): Provider|string|null
    {
        return null;
    }

    /**
     * Model for voice sessions. Null falls back to config default.
     */
    public function voiceModel(): ?string
    {
        return null;
    }

    /**
     * Voice name for voice sessions (e.g., 'alloy', 'shimmer', 'marin').
     */
    public function voice(): ?string
    {
        return null;
    }

    // ─── Config ─────────────────────────────────────────────────────

    /**
     * Sampling temperature. Null falls back to provider default.
     */
    public function temperature(): ?float
    {
        return null;
    }

    /**
     * Maximum tokens in the response. Null falls back to provider default.
     */
    public function maxTokens(): ?int
    {
        return null;
    }

    /**
     * Max round trips in the tool call loop. Null = unlimited.
     */
    public function maxSteps(): ?int
    {
        return null;
    }

    /**
     * Execute tool calls concurrently. False by default for safe persistence tracking.
     * Set to true when tools are independent and you want concurrent execution.
     */
    public function concurrent(): bool
    {
        return false;
    }

    /**
     * Provider-specific options passed through directly.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(): array
    {
        return [];
    }
}
