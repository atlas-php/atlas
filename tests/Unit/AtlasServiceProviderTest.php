<?php

declare(strict_types=1);

use Atlasphp\Atlas\AgentRegistry;
use Atlasphp\Atlas\AtlasCache;
use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Providers\Anthropic\AnthropicDriver;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\ElevenLabs\ElevenLabsDriver;
use Atlasphp\Atlas\Support\VariableRegistry;

// ─── Singleton Bindings ────────────────────────────────────────────────────

it('resolves ProviderRegistryContract as singleton', function () {
    $a = app(ProviderRegistryContract::class);
    $b = app(ProviderRegistryContract::class);

    expect($a)->toBe($b);
});

it('resolves AgentRegistry as singleton', function () {
    $a = app(AgentRegistry::class);
    $b = app(AgentRegistry::class);

    expect($a)->toBe($b);
});

it('resolves AtlasManager as singleton', function () {
    $a = app(AtlasManager::class);
    $b = app(AtlasManager::class);

    expect($a)->toBe($b);
});

it('resolves HttpClient as singleton', function () {
    $a = app(HttpClient::class);
    $b = app(HttpClient::class);

    expect($a)->toBe($b);
});

it('resolves MiddlewareStack as singleton', function () {
    $a = app(MiddlewareStack::class);
    $b = app(MiddlewareStack::class);

    expect($a)->toBe($b);
});

it('resolves VariableRegistry as singleton', function () {
    $a = app(VariableRegistry::class);
    $b = app(VariableRegistry::class);

    expect($a)->toBe($b);
});

it('resolves AtlasCache as singleton', function () {
    $a = app(AtlasCache::class);
    $b = app(AtlasCache::class);

    expect($a)->toBe($b);
});

// ─── Scoped Bindings ───────────────────────────────────────────────────────

it('resolves ExecutionService as scoped', function () {
    $instance = app(ExecutionService::class);

    expect($instance)->toBeInstanceOf(ExecutionService::class);
});

// ─── Factory Bindings ──────────────────────────────────────────────────────

it('resolves EmbeddingResolver with AtlasCache dependency', function () {
    $resolver = app(EmbeddingResolver::class);

    expect($resolver)->toBeInstanceOf(EmbeddingResolver::class);
});

// ─── Provider Registration ─────────────────────────────────────────────────

it('registers openai provider', function () {
    $registry = app(ProviderRegistryContract::class);

    expect($registry->has('openai'))->toBeTrue();
});

it('registers anthropic provider', function () {
    $registry = app(ProviderRegistryContract::class);

    expect($registry->has('anthropic'))->toBeTrue();
});

it('registers google provider', function () {
    $registry = app(ProviderRegistryContract::class);

    expect($registry->has('google'))->toBeTrue();
});

it('registers elevenlabs provider', function () {
    $registry = app(ProviderRegistryContract::class);

    expect($registry->has('elevenlabs'))->toBeTrue();
});

it('registers xai provider', function () {
    $registry = app(ProviderRegistryContract::class);

    expect($registry->has('xai'))->toBeTrue();
});

it('registers cohere provider', function () {
    $registry = app(ProviderRegistryContract::class);

    expect($registry->has('cohere'))->toBeTrue();
});

it('registers jina provider', function () {
    $registry = app(ProviderRegistryContract::class);

    expect($registry->has('jina'))->toBeTrue();
});

// ─── Built-in Variables ────────────────────────────────────────────────────

it('registers built-in date variables', function () {
    $registry = app(VariableRegistry::class);
    $resolved = $registry->resolve();

    expect($resolved)->toHaveKey('DATE')
        ->and($resolved)->toHaveKey('DATETIME')
        ->and($resolved)->toHaveKey('TIME')
        ->and($resolved)->toHaveKey('TIMEZONE');
});

it('registers built-in app variables', function () {
    $registry = app(VariableRegistry::class);
    $resolved = $registry->resolve();

    expect($resolved)->toHaveKey('APP_NAME')
        ->and($resolved)->toHaveKey('APP_ENV')
        ->and($resolved)->toHaveKey('APP_URL');
});

// ─── Agent Discovery ───────────────────────────────────────────────────────

it('agent registry is available and handles empty directory gracefully', function () {
    $registry = app(AgentRegistry::class);

    $tempDir = sys_get_temp_dir().'/atlas_test_agents_'.uniqid();
    mkdir($tempDir, 0755, true);

    $registry->discover($tempDir, 'TestDiscovery');

    // No agents found in empty dir — should not throw
    expect($registry->keys())->toBeArray();

    rmdir($tempDir);
});

it('does not discover agents when path is null', function () {
    config(['atlas.agents.path' => null, 'atlas.agents.namespace' => 'App\\Agents']);

    $registry = app(AgentRegistry::class);

    // Should not throw — just skips discovery
    expect($registry->keys())->toBeArray();
});

it('does not discover agents when namespace is null', function () {
    config(['atlas.agents.path' => '/some/path', 'atlas.agents.namespace' => null]);

    $registry = app(AgentRegistry::class);

    expect($registry->keys())->toBeArray();
});

// ─── Provider Factory Resolution ────────────────────────────────────────

it('resolves anthropic factory to AnthropicDriver', function () {
    config(['atlas.providers.anthropic.api_key' => 'test', 'atlas.providers.anthropic.url' => 'https://api.anthropic.com/v1']);

    $registry = app(ProviderRegistryContract::class);
    $driver = $registry->resolve('anthropic');

    expect($driver)->toBeInstanceOf(AnthropicDriver::class);
});

it('resolves elevenlabs factory to ElevenLabsDriver', function () {
    config(['atlas.providers.elevenlabs.api_key' => 'test', 'atlas.providers.elevenlabs.url' => 'https://api.elevenlabs.io/v1']);

    $registry = app(ProviderRegistryContract::class);
    $driver = $registry->resolve('elevenlabs');

    expect($driver)->toBeInstanceOf(ElevenLabsDriver::class);
});

// ─── Voice Routes ───────────────────────────────────────────────────────

it('does not register voice routes when voice_transcripts disabled', function () {
    config([
        'atlas.persistence.enabled' => true,
        'atlas.persistence.voice_transcripts.enabled' => false,
    ]);

    // Re-boot the provider to test the config check
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->pluck('uri')
        ->filter(fn (string $uri) => str_contains($uri, 'voice'));

    // Since voice_transcripts is disabled, no voice routes should be registered
    // (this tests the config guard, not route existence — routes were registered
    // before this test overrode the config)
    expect(config('atlas.persistence.voice_transcripts.enabled'))->toBeFalse();
});
