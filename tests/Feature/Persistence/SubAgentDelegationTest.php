<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\AgentRegistry;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
use Atlasphp\Atlas\Persistence\Middleware\PersistConversation;
use Atlasphp\Atlas\Persistence\Middleware\TrackExecution;
use Atlasphp\Atlas\Persistence\Middleware\TrackProviderCall;
use Atlasphp\Atlas\Persistence\Middleware\TrackStep;
use Atlasphp\Atlas\Persistence\Middleware\TrackToolCall;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Illuminate\Contracts\Events\Dispatcher;

// ─── Test agents ────────────────────────────────────────────────────────────

class DelegateSpecialistAgent extends Agent
{
    public function key(): string
    {
        return 'specialist-d';
    }

    public function instructions(): ?string
    {
        return 'You know secret codes.';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o-mini';
    }
}

class DelegateOrchestratorAgent extends Agent
{
    public function key(): string
    {
        return 'orchestrator-d';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }

    public function maxSteps(): ?int
    {
        return 5;
    }

    public function tools(): array
    {
        return [DelegateSpecialistAgent::class];
    }
}

class VarSpecialistAgent extends Agent
{
    public function key(): string
    {
        return 'var-specialist';
    }

    public function instructions(): ?string
    {
        return 'You are speaking with {user_name}. Answer their question.';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o-mini';
    }
}

class VarOrchestratorAgent extends DelegateOrchestratorAgent
{
    public function key(): string
    {
        return 'var-orchestrator';
    }

    public function tools(): array
    {
        return [VarSpecialistAgent::class];
    }
}

function makeDelegationRequest(string $key): AgentRequest
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

/** Fake sequence: orchestrator delegates → specialist answers → orchestrator finalises. */
function fakeDelegationConversation(): AtlasFake
{
    return new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withToolCalls([
            new ToolCall(id: 'call_d1', name: 'specialist-d', arguments: ['task' => 'find the code']),
        ]),
        TextResponseFake::make()->withText('the code is ZULU')->withUsage(new Usage(inputTokens: 3, outputTokens: 4)),
        TextResponseFake::make()->withText('Done. The code is ZULU.')->withUsage(new Usage(inputTokens: 10, outputTokens: 5)),
    ]);
}

beforeEach(function () {
    // PersistenceTestCase enables persistence after the provider registers, so the
    // tracking middleware isn't in the AtlasConfig snapshot. Wire it up explicitly.
    config(['atlas.middleware' => [
        PersistConversation::class,
        TrackExecution::class,
        TrackStep::class,
        TrackToolCall::class,
        TrackProviderCall::class,
    ]]);
    AtlasConfig::refresh();

    app(AgentRegistry::class)->register(DelegateSpecialistAgent::class);
    app(AgentRegistry::class)->register(DelegateOrchestratorAgent::class);
    app(AgentRegistry::class)->register(VarSpecialistAgent::class);
    app(AgentRegistry::class)->register(VarOrchestratorAgent::class);
});

it('delegates to a sub-agent and returns its contribution', function () {
    fakeDelegationConversation();

    $response = makeDelegationRequest('orchestrator-d')->message('what is the code?')->asText();

    expect($response->text)->toContain('ZULU');
});

it('records an auditable parent → child execution tree', function () {
    fakeDelegationConversation();

    makeDelegationRequest('orchestrator-d')->message('what is the code?')->asText();

    expect(Execution::count())->toBe(2);

    $parent = Execution::whereNull('parent_execution_id')->firstOrFail();
    $child = Execution::whereNotNull('parent_execution_id')->firstOrFail();

    // Lineage.
    expect($parent->agent)->toBe('orchestrator-d')
        ->and($parent->depth)->toBe(0)
        ->and($child->agent)->toBe('specialist-d')
        ->and($child->depth)->toBe(1)
        ->and($child->parent_execution_id)->toBe($parent->id);

    // The delegating tool call is typed 'agent', links the child, and its
    // recorded result is exactly the sub-agent's answer handed back to the parent.
    $delegation = ExecutionToolCall::where('type', ToolCallType::Agent->value)->firstOrFail();
    expect($delegation->name)->toBe('specialist-d')
        ->and($child->parent_tool_call_id)->toBe($delegation->id)
        ->and($delegation->result)->toBe('the code is ZULU');

    // Tree relations + usage roll-up.
    expect($parent->children)->toHaveCount(1)
        ->and($parent->children->first()->id)->toBe($child->id);

    // Tree-summed usage rolls the child's tokens into the parent's own total.
    $ownTotal = ($parent->usage['input_tokens'] ?? 0) + ($parent->usage['output_tokens'] ?? 0);
    $childTotal = ($child->usage['input_tokens'] ?? 0) + ($child->usage['output_tokens'] ?? 0);
    expect($childTotal)->toBeGreaterThan(0)
        ->and($parent->totalUsage()['total_tokens'])->toBe($ownTotal + $childTotal)
        ->and($parent->totalUsage()['total_tokens'])->toBeGreaterThan($ownTotal);
});

it('runs the sub-agent in isolation from the parent conversation', function () {
    $fake = fakeDelegationConversation();

    makeDelegationRequest('orchestrator-d')->message('what is the code?')->asText();

    // The specialist request (gpt-4o-mini) carries only the delegated task,
    // not the orchestrator's user message.
    $specialistRequests = array_filter(
        $fake->driver('openai')->recorded(),
        fn ($r) => $r->model === 'gpt-4o-mini',
    );

    expect($specialistRequests)->toHaveCount(1);

    $request = array_values($specialistRequests)[0]->request;
    expect($request->message)->toBe('find the code')
        ->and($request->message)->not->toContain('what is the code?');
});

it('lets the parent recover and still respond when a delegation fails', function () {
    // Block delegation so the sub-agent call fails inside the tool.
    config(['atlas.agents.max_delegation_depth' => 0]);

    new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withToolCalls([
            new ToolCall(id: 'call_f1', name: 'specialist-d', arguments: ['task' => 'find the code']),
        ]),
        TextResponseFake::make()->withText('Sorry, I could not reach the specialist.'),
    ]);

    $response = makeDelegationRequest('orchestrator-d')->message('what is the code?')->asText();

    // The parent received the failure as a tool result and produced a final answer.
    expect($response->text)->toContain('Sorry');

    // No child execution was created; the delegating tool call is recorded as failed.
    expect(Execution::whereNotNull('parent_execution_id')->count())->toBe(0);

    $delegation = ExecutionToolCall::where('type', ToolCallType::Agent->value)->firstOrFail();
    expect($delegation->status)->toBe(ExecutionStatus::Failed)
        ->and($delegation->result)->toContain('maximum depth');
});

it('forwards parent per-call variables into the sub-agent prompt', function () {
    $fake = new AtlasFake(app(ProviderRegistryContract::class), [
        TextResponseFake::make()->withToolCalls([
            new ToolCall(id: 'call_v1', name: 'var-specialist', arguments: ['task' => 'say hi']),
        ]),
        TextResponseFake::make()->withText('Hi!'),
        TextResponseFake::make()->withText('Done.'),
    ]);

    // user_name is set ONLY via the parent's per-call withVariables() — it must
    // still reach the sub-agent's prompt.
    makeDelegationRequest('var-orchestrator')
        ->withVariables(['user_name' => 'Tim'])
        ->message('greet me')
        ->asText();

    // The sub-agent (gpt-4o-mini) received fully interpolated instructions,
    // exactly as if it had been called as a top-level agent.
    $specialistRequests = array_values(array_filter(
        $fake->driver('openai')->recorded(),
        fn ($r) => $r->model === 'gpt-4o-mini',
    ));

    expect($specialistRequests)->toHaveCount(1)
        ->and($specialistRequests[0]->request->instructions)
        ->toBe('You are speaking with Tim. Answer their question.');
});
