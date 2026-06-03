<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\DelegationCycleException;
use Atlasphp\Atlas\Exceptions\MaxDelegationDepthException;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Atlasphp\Atlas\Tools\AgentTool;

// ─── Test agents ────────────────────────────────────────────────────────────

class SpecialistTestAgent extends Agent
{
    public function key(): string
    {
        return 'specialist';
    }

    public function description(): ?string
    {
        return 'Answers domain questions.';
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

class PlainSubAgent extends Agent
{
    public function key(): string
    {
        return 'plain-sub';
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

/** Installs a fake driver that returns the given text for any provider call. */
function fakeSubAgentText(string $text): void
{
    new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withText($text),
    ]);
}

/** Registers a driver whose text() always throws, for error-path tests. */
function installThrowingDriver(): void
{
    app(ProviderRegistryContract::class)->register('openai', fn () => new class extends Driver
    {
        public function __construct() {}

        public function text(TextRequest $request): TextResponse
        {
            throw new RuntimeException('boom');
        }

        public function capabilities(): ProviderCapabilities
        {
            throw new RuntimeException('n/a');
        }

        public function name(): string
        {
            return 'openai';
        }
    });
}

// ─── name / description ──────────────────────────────────────────────────────

it('uses the agent key as the tool name by default', function () {
    expect(AgentTool::for(new SpecialistTestAgent)->name())->toBe('specialist');
});

it('allows overriding the tool name', function () {
    expect(AgentTool::for(new SpecialistTestAgent, 'ask_specialist')->name())->toBe('ask_specialist');
});

it('uses the agent description when present', function () {
    expect(AgentTool::for(new SpecialistTestAgent)->description())->toBe('Answers domain questions.');
});

it('falls back to a generic delegation description', function () {
    $description = AgentTool::for(new PlainSubAgent)->description();

    expect($description)->toContain('plain-sub')
        ->and($description)->toContain('isolation');
});

it('allows overriding the description', function () {
    expect(AgentTool::for(new PlainSubAgent, null, 'Custom.')->description())->toBe('Custom.');
});

// ─── parameters / schema ─────────────────────────────────────────────────────

it('exposes a single required task string parameter', function () {
    $tool = AgentTool::for(new SpecialistTestAgent);
    $params = $tool->parameters();

    expect($params)->toHaveCount(1)
        ->and($params[0]->name())->toBe('task')
        ->and($params[0]->isRequired())->toBeTrue();

    $schema = $tool->toDefinition()->parameters;
    expect($schema['type'])->toBe('object')
        ->and($schema['properties'])->toHaveKey('task')
        ->and($schema['properties']['task']['type'])->toBe('string')
        ->and($schema['required'])->toBe(['task']);
});

it('is flagged as a delegation tool', function () {
    expect(AgentTool::for(new SpecialistTestAgent)->isDelegation())->toBeTrue();
});

// ─── handle: happy path ──────────────────────────────────────────────────────

it('runs the sub-agent with the task and returns its text', function () {
    fakeSubAgentText('the secret is 42');

    $result = AgentTool::for(new SpecialistTestAgent)->handle(['task' => 'reveal the secret'], []);

    expect($result)->toBe('the secret is 42');
});

it('handles a missing task argument without crashing', function () {
    fakeSubAgentText('handled empty');

    $result = AgentTool::for(new SpecialistTestAgent)->handle([], []);

    expect($result)->toBe('handled empty');
});

// ─── handle: depth + cycle guards ────────────────────────────────────────────

it('throws when the delegation depth limit is reached', function () {
    config()->set('atlas.agents.max_delegation_depth', 2);

    AgentTool::for(new SpecialistTestAgent)->handle(
        ['task' => 'x'],
        [AgentTool::DEPTH_META_KEY => 2],
    );
})->throws(MaxDelegationDepthException::class);

it('runs when below the depth limit', function () {
    config()->set('atlas.agents.max_delegation_depth', 2);
    fakeSubAgentText('ok');

    $result = AgentTool::for(new SpecialistTestAgent)->handle(
        ['task' => 'x'],
        [AgentTool::DEPTH_META_KEY => 1],
    );

    expect($result)->toBe('ok');
});

it('throws when the agent is already in the delegation chain', function () {
    AgentTool::for(new SpecialistTestAgent)->handle(
        ['task' => 'x'],
        [AgentTool::CHAIN_META_KEY => ['orchestrator', 'specialist']],
    );
})->throws(DelegationCycleException::class);

// ─── handle: error modes ─────────────────────────────────────────────────────

it('propagates sub-agent errors when delegation_errors is throw', function () {
    config()->set('atlas.agents.delegation_errors', 'throw');
    installThrowingDriver();

    AgentTool::for(new SpecialistTestAgent)->handle(['task' => 'x'], []);
})->throws(RuntimeException::class, 'boom');

it('returns the error as a string when delegation_errors is return', function () {
    config()->set('atlas.agents.delegation_errors', 'return');
    installThrowingDriver();

    $result = AgentTool::for(new SpecialistTestAgent)->handle(['task' => 'x'], []);

    expect($result)->toContain("Sub-agent 'specialist' failed")
        ->and($result)->toContain('boom');
});
