<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\AgentRegistry;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\AgentNotFoundException;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Atlasphp\Atlas\Tools\AgentTool;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Contracts\Events\Dispatcher;

class WrapSpecialistAgent extends Agent
{
    public function key(): string
    {
        return 'wrap-specialist';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }
}

class WrapPlainSubAgent extends Agent
{
    public function key(): string
    {
        return 'wrap-plain';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }
}

class WrapEchoTool extends Tool
{
    public function name(): string
    {
        return 'echo';
    }

    public function description(): string
    {
        return 'Echoes input.';
    }

    public function handle(array $args, array $context): mixed
    {
        return 'echo';
    }
}

function makeSubAgentRequest(string $key = 'host'): AgentRequest
{
    return new AgentRequest(
        key: $key,
        agentRegistry: app(AgentRegistry::class),
        providerRegistry: app(ProviderRegistryContract::class),
        app: app(),
        events: app(Dispatcher::class),
        config: app(AtlasConfig::class),
    );
}

/**
 * @return array<int, Tool>
 */
function resolveToolsFor(Agent $agent): array
{
    $request = makeSubAgentRequest();
    $method = new ReflectionMethod($request, 'resolveTools');
    $method->setAccessible(true);

    /** @var array<int, Tool> $tools */
    $tools = $method->invoke($request, $agent);

    return $tools;
}

it('auto-wraps an Agent instance in tools() as an AgentTool', function () {
    $agent = new class extends Agent
    {
        public function tools(): array
        {
            return [new WrapSpecialistAgent];
        }
    };

    $tools = resolveToolsFor($agent);

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(AgentTool::class)
        ->and($tools[0]->name())->toBe('wrap-specialist')
        ->and($tools[0]->isDelegation())->toBeTrue();
});

it('auto-wraps an Agent class-string in tools() as an AgentTool', function () {
    $agent = new class extends Agent
    {
        public function tools(): array
        {
            return [WrapPlainSubAgent::class];
        }
    };

    $tools = resolveToolsFor($agent);

    expect($tools[0])->toBeInstanceOf(AgentTool::class)
        ->and($tools[0]->name())->toBe('wrap-plain');
});

it('passes through an explicit AgentTool and normal tools unchanged', function () {
    $agent = new class extends Agent
    {
        public function tools(): array
        {
            return [
                AgentTool::for(new WrapPlainSubAgent, 'ask_plain'),
                new WrapEchoTool,
                WrapEchoTool::class,
            ];
        }
    };

    $tools = resolveToolsFor($agent);

    expect($tools)->toHaveCount(3)
        ->and($tools[0])->toBeInstanceOf(AgentTool::class)
        ->and($tools[0]->name())->toBe('ask_plain')
        ->and($tools[1])->toBeInstanceOf(WrapEchoTool::class)
        ->and($tools[1]->isDelegation())->toBeFalse()
        ->and($tools[2])->toBeInstanceOf(WrapEchoTool::class);
});

it('runs an unregistered agent instance via forInstance()', function () {
    new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText('ran via instance'),
    ]);

    // WrapSpecialistAgent is NOT registered in the AgentRegistry.
    $response = Atlas::agent('wrap-specialist')
        ->forInstance(new WrapSpecialistAgent)
        ->message('hello')
        ->asText();

    expect($response->text)->toBe('ran via instance');
});

it('throws for an unregistered key without forInstance', function () {
    makeSubAgentRequest('definitely-not-registered')->message('hi')->asText();
})->throws(AgentNotFoundException::class);

it('resolves provider and model from the instance, not the registry', function () {
    // Key is not registered — only forInstance() can satisfy these.
    $request = makeSubAgentRequest('definitely-not-registered')->forInstance(new WrapSpecialistAgent);

    $providerKey = new ReflectionMethod($request, 'resolveProviderKey');
    $providerKey->setAccessible(true);
    $modelKey = new ReflectionMethod($request, 'resolveModelKey');
    $modelKey->setAccessible(true);

    expect($providerKey->invoke($request))->toBe('openai')
        ->and($modelKey->invoke($request))->toBe('gpt-4o');
});
