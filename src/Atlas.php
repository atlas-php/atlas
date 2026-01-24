<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\PendingAgentRequest;
use Atlasphp\Atlas\Testing\AtlasFake;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for Atlas functionality.
 *
 * @method static PendingAgentRequest agent(string|AgentContract $agent)
 *
 * @mixin \Atlasphp\Atlas\AtlasManager
 *
 * @see \Atlasphp\Atlas\AtlasManager
 */
class Atlas extends Facade
{
    /**
     * The active fake instance.
     */
    protected static ?AtlasFake $fake = null;

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return AtlasManager::class;
    }

    /**
     * Create a fake instance for testing.
     *
     * @param  array<int, AgentResponse>|null  $responses  Optional default responses.
     */
    public static function fake(?array $responses = null): AtlasFake
    {
        $fake = new AtlasFake(static::getFacadeApplication());

        if ($responses !== null) {
            $fake->sequence($responses);
        }

        $fake->activate();
        self::$fake = $fake;

        return $fake;
    }

    /**
     * Restore the real Atlas implementation.
     */
    public static function unfake(): void
    {
        if (self::$fake !== null) {
            self::$fake->restore();
            self::$fake = null;
        }
    }

    /**
     * Get the current fake instance if active.
     */
    public static function getFake(): ?AtlasFake
    {
        return self::$fake;
    }

    /**
     * Check if Atlas is currently faked.
     */
    public static function isFaked(): bool
    {
        return self::$fake !== null;
    }
}
