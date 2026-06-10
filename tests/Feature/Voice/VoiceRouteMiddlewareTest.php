<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\AtlasServiceProvider;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

// ─── Helpers ─────────────────────────────────────────────────────

/**
 * Return the most recently registered voice tool route, or null.
 */
function voiceToolRoute(): ?RoutingRoute
{
    $match = null;

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if ($route->uri() === 'atlas/voice/{sessionId}/tool') {
            $match = $route;
        }
    }

    return $match;
}

/**
 * Re-run only the voice route registration with the current config.
 * The app is already booted, so the booted() callback fires immediately.
 */
function reregisterVoiceRoutes(object $app): void
{
    AtlasConfig::refresh();

    $provider = new AtlasServiceProvider($app);
    $method = new ReflectionMethod($provider, 'registerVoiceRoutes');
    $method->setAccessible(true);
    $method->invoke($provider);
}

// ─── Tests ───────────────────────────────────────────────────────

it('registers voice routes with no middleware by default', function () {
    $route = voiceToolRoute();

    expect($route)->not->toBeNull();
    expect($route->middleware())->toBe([]);
});

it('applies configured voice.route_middleware to the voice routes', function () {
    config()->set('atlas.voice.route_middleware', ['auth:sanctum', 'throttle:60,1']);

    reregisterVoiceRoutes($this->app);

    $route = voiceToolRoute();

    expect($route)->not->toBeNull();
    expect($route->middleware())->toContain('auth:sanctum');
    expect($route->middleware())->toContain('throttle:60,1');
});

it('reads voice.route_middleware onto AtlasConfig', function () {
    config()->set('atlas.voice.route_middleware', ['auth:sanctum']);

    $config = AtlasConfig::refresh();

    expect($config->voiceRouteMiddleware)->toBe(['auth:sanctum']);
});

it('defaults voice.route_middleware to an empty array', function () {
    expect(app(AtlasConfig::class)->voiceRouteMiddleware)->toBe([]);
});

// ─── Backward compatibility with the legacy persistence.voice_* keys ────────

it('falls back to legacy persistence.voice_route_prefix and voice_session_ttl', function () {
    config()->set('atlas.voice', null);
    config()->set('atlas.persistence.voice_route_prefix', 'legacy-prefix');
    config()->set('atlas.persistence.voice_session_ttl', 99);

    $config = AtlasConfig::refresh();

    expect($config->voiceRoutePrefix)->toBe('legacy-prefix')
        ->and($config->voiceSessionTtl)->toBe(99);
});

it('prefers the new voice block over the legacy persistence keys', function () {
    config()->set('atlas.voice.route_prefix', 'new-prefix');
    config()->set('atlas.voice.session_ttl', 42);
    config()->set('atlas.persistence.voice_route_prefix', 'legacy-prefix');
    config()->set('atlas.persistence.voice_session_ttl', 99);

    $config = AtlasConfig::refresh();

    expect($config->voiceRoutePrefix)->toBe('new-prefix')
        ->and($config->voiceSessionTtl)->toBe(42);
});
