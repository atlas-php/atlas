<?php

declare(strict_types=1);

use Atlasphp\Atlas\AgentRegistry;
use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\AtlasServiceProvider;
use Atlasphp\Atlas\Providers\Cohere\CohereDriver;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Google\GoogleDriver;
use Atlasphp\Atlas\Providers\Jina\JinaDriver;
use Atlasphp\Atlas\Providers\OpenAi\OpenAiDriver;
use Atlasphp\Atlas\Providers\Xai\XaiDriver;

it('registers ProviderRegistryContract as a singleton', function () {
    $first = $this->app->make(ProviderRegistryContract::class);
    $second = $this->app->make(ProviderRegistryContract::class);

    expect($first)->toBeInstanceOf(ProviderRegistryContract::class);
    expect($first)->toBe($second);
});

it('registers AtlasManager as a singleton', function () {
    $first = $this->app->make(AtlasManager::class);
    $second = $this->app->make(AtlasManager::class);

    expect($first)->toBeInstanceOf(AtlasManager::class);
    expect($first)->toBe($second);
});

it('merges the atlas config', function () {
    expect(config('atlas.defaults'))->not->toBeNull();
    expect(config('atlas.providers'))->not->toBeNull();
});

it('registers the openai provider factory', function () {
    $registry = $this->app->make(ProviderRegistryContract::class);

    expect($registry->has('openai'))->toBeTrue();
});

it('resolves openai to OpenAiDriver', function () {
    config()->set('atlas.providers.openai', [
        'api_key' => 'test-key',
        'url' => 'https://api.openai.com/v1',
    ]);

    $registry = $this->app->make(ProviderRegistryContract::class);
    $driver = $registry->resolve('openai');

    expect($driver)->toBeInstanceOf(OpenAiDriver::class);
    expect($driver->name())->toBe('openai');
});

it('registers the xai provider factory', function () {
    $registry = $this->app->make(ProviderRegistryContract::class);

    expect($registry->has('xai'))->toBeTrue();
});

it('resolves xai to XaiDriver', function () {
    config()->set('atlas.providers.xai', [
        'api_key' => 'test-key',
        'url' => 'https://api.x.ai/v1',
    ]);

    $registry = $this->app->make(ProviderRegistryContract::class);
    $driver = $registry->resolve('xai');

    expect($driver)->toBeInstanceOf(XaiDriver::class);
    expect($driver->name())->toBe('xai');
});

it('registers the google provider factory', function () {
    $registry = $this->app->make(ProviderRegistryContract::class);

    expect($registry->has('google'))->toBeTrue();
});

it('resolves google to GoogleDriver', function () {
    config()->set('atlas.providers.google', [
        'api_key' => 'test-key',
        'url' => 'https://generativelanguage.googleapis.com',
    ]);

    $registry = $this->app->make(ProviderRegistryContract::class);
    $driver = $registry->resolve('google');

    expect($driver)->toBeInstanceOf(GoogleDriver::class);
    expect($driver->name())->toBe('google');
});

it('registers the cohere provider factory', function () {
    $registry = $this->app->make(ProviderRegistryContract::class);

    expect($registry->has('cohere'))->toBeTrue();
});

it('resolves cohere to CohereDriver', function () {
    config()->set('atlas.providers.cohere', [
        'api_key' => 'test-key',
        'url' => 'https://api.cohere.com',
    ]);

    $registry = $this->app->make(ProviderRegistryContract::class);
    $driver = $registry->resolve('cohere');

    expect($driver)->toBeInstanceOf(CohereDriver::class);
    expect($driver->name())->toBe('cohere');
});

it('registers the jina provider factory', function () {
    $registry = $this->app->make(ProviderRegistryContract::class);

    expect($registry->has('jina'))->toBeTrue();
});

it('resolves jina to JinaDriver', function () {
    config()->set('atlas.providers.jina', [
        'api_key' => 'test-key',
        'url' => 'https://api.jina.ai',
    ]);

    $registry = $this->app->make(ProviderRegistryContract::class);
    $driver = $registry->resolve('jina');

    expect($driver)->toBeInstanceOf(JinaDriver::class);
    expect($driver->name())->toBe('jina');
});

it('discovers agents from configured directory', function () {
    $tmpDir = sys_get_temp_dir().'/atlas_test_agents_'.uniqid();
    mkdir($tmpDir, 0755, true);

    $agentCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace AtlasTestAgents;

use Atlasphp\Atlas\Agent;

class DiscoveryTestAgent extends Agent
{
}
PHP;

    file_put_contents($tmpDir.'/DiscoveryTestAgent.php', $agentCode);

    // Require the file so the class is available to class_exists / is_subclass_of
    require_once $tmpDir.'/DiscoveryTestAgent.php';

    config()->set('atlas.agents.path', $tmpDir);
    config()->set('atlas.agents.namespace', 'AtlasTestAgents');

    // Re-trigger discovery by calling discoverAgents via a fresh registry
    $registry = app(AgentRegistry::class);
    $registry->discover($tmpDir, 'AtlasTestAgents');

    expect($registry->has('discovery-test'))->toBeTrue();

    // Cleanup
    @unlink($tmpDir.'/DiscoveryTestAgent.php');
    @rmdir($tmpDir);
});

it('skips discovery when agents path is null', function () {
    config()->set('atlas.agents.path', null);
    config()->set('atlas.agents.namespace', 'App\\Agents');

    // Re-register the provider to trigger discoverAgents with null path
    $provider = new AtlasServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    // Should not throw — just silently skip
    expect(app(AgentRegistry::class))->toBeInstanceOf(AgentRegistry::class);
});

it('skips discovery when agents namespace is null', function () {
    config()->set('atlas.agents.path', '/some/path');
    config()->set('atlas.agents.namespace', null);

    // Re-register the provider to trigger discoverAgents with null namespace
    $provider = new AtlasServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    // Should not throw — just silently skip
    expect(app(AgentRegistry::class))->toBeInstanceOf(AgentRegistry::class);
});
