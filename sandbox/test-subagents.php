<?php

declare(strict_types=1);

/**
 * Sub-Agents (Agent-as-Tool) Integration Test
 *
 * Validates agent delegation against the real OpenAI API: implicit + explicit
 * wiring, isolated standalone runs, the auditable parent → child execution
 * tree (persistence lineage), usage roll-up, and the depth/cycle guards.
 *
 * Usage: php test-subagents.php
 *
 * Requires OPENAI_API_KEY in sandbox/.env and a migrated database.
 */
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\AgentRegistry;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Events\AgentToolCallCompleted;
use Atlasphp\Atlas\Events\AgentToolCallStarted;
use Atlasphp\Atlas\Exceptions\DelegationCycleException;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
use Atlasphp\Atlas\Persistence\Middleware\PersistConversation;
use Atlasphp\Atlas\Persistence\Middleware\TrackExecution;
use Atlasphp\Atlas\Persistence\Middleware\TrackProviderCall;
use Atlasphp\Atlas\Persistence\Middleware\TrackStep;
use Atlasphp\Atlas\Persistence\Middleware\TrackToolCall;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Schema\Fields\IntegerField;
use Atlasphp\Atlas\Tools\AgentTool;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Event;

$app = require __DIR__.'/bootstrap.php';

$app['config']->set('atlas.providers', [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],
]);

// Guarantee execution tracking is wired (the testbench bootstrap enables
// persistence after the provider registers, so the snapshot can be empty).
$app['config']->set('atlas.persistence.enabled', true);
$app['config']->set('atlas.middleware', [
    PersistConversation::class,
    TrackExecution::class,
    TrackStep::class,
    TrackToolCall::class,
    TrackProviderCall::class,
]);
AtlasConfig::refresh();

// ─── Agents ──────────────────────────────────────────────────────────────────

class VaultSpecialistAgent extends Agent
{
    public function key(): string
    {
        return 'vault';
    }

    public function description(): ?string
    {
        return 'Knows the secret project passphrase.';
    }

    public function instructions(): ?string
    {
        return 'You are the project vault. When asked for the project passphrase, reply with '
            .'exactly: "The project passphrase is ZULU-7." Do not add anything else.';
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

class RouterOrchestratorAgent extends Agent
{
    public function key(): string
    {
        return 'router';
    }

    public function instructions(): ?string
    {
        return 'You are a router and do NOT know any secrets yourself. To answer ANY question '
            .'about the project passphrase you MUST call the vault sub-agent tool with a clear '
            .'task, then relay its exact answer to the user.';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o-mini';
    }

    public function maxSteps(): ?int
    {
        return 4;
    }

    public function tools(): array
    {
        return [VaultSpecialistAgent::class];
    }
}

class RouterExplicitAgent extends RouterOrchestratorAgent
{
    public function key(): string
    {
        return 'router-explicit';
    }

    public function tools(): array
    {
        return [AgentTool::for(new VaultSpecialistAgent, 'ask_vault', 'Ask the vault for the passphrase.')];
    }
}

class AddNumbersTool extends Tool
{
    public function name(): string
    {
        return 'add_numbers';
    }

    public function description(): string
    {
        return 'Adds two integers and returns the sum.';
    }

    public function parameters(): array
    {
        return [
            new IntegerField('a', 'First number.'),
            new IntegerField('b', 'Second number.'),
        ];
    }

    public function handle(array $args, array $context): mixed
    {
        return (int) ($args['a'] ?? 0) + (int) ($args['b'] ?? 0);
    }
}

class RouterMixedAgent extends RouterOrchestratorAgent
{
    public function key(): string
    {
        return 'router-mixed';
    }

    public function tools(): array
    {
        return [VaultSpecialistAgent::class, AddNumbersTool::class];
    }
}

// Multi-level chain: coordinator → researcher → vault (depths 0 → 1 → 2).

class ResearcherAgent extends Agent
{
    public function key(): string
    {
        return 'researcher';
    }

    public function instructions(): ?string
    {
        return 'You research answers but do NOT know secrets. To answer any question about the '
            .'project passphrase you MUST call the vault sub-agent tool, then relay its exact answer.';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o-mini';
    }

    public function maxSteps(): ?int
    {
        return 4;
    }

    public function tools(): array
    {
        return [VaultSpecialistAgent::class];
    }
}

class CoordinatorAgent extends ResearcherAgent
{
    public function key(): string
    {
        return 'coordinator';
    }

    public function instructions(): ?string
    {
        return 'You coordinate work but do NOT know secrets. To answer any question about the '
            .'project passphrase you MUST call the researcher sub-agent tool, then relay its exact answer.';
    }

    public function tools(): array
    {
        return [ResearcherAgent::class];
    }
}

// Dynamic prompt injection: the sub-agent's instructions use a {user_name} macro.

class GreeterSpecialistAgent extends VaultSpecialistAgent
{
    public function key(): string
    {
        return 'greeter';
    }

    public function instructions(): ?string
    {
        return 'You are speaking with {user_name}. When asked for the passphrase, reply with '
            .'exactly: "{user_name}, the passphrase is ZULU-7." Use their real name, not the placeholder.';
    }
}

class GreeterRouterAgent extends RouterOrchestratorAgent
{
    public function key(): string
    {
        return 'greeter-router';
    }

    public function tools(): array
    {
        return [GreeterSpecialistAgent::class];
    }
}

app(AgentRegistry::class)->register(VaultSpecialistAgent::class);
app(AgentRegistry::class)->register(RouterOrchestratorAgent::class);
app(AgentRegistry::class)->register(RouterExplicitAgent::class);
app(AgentRegistry::class)->register(RouterMixedAgent::class);
app(AgentRegistry::class)->register(ResearcherAgent::class);
app(AgentRegistry::class)->register(CoordinatorAgent::class);
app(AgentRegistry::class)->register(GreeterSpecialistAgent::class);
app(AgentRegistry::class)->register(GreeterRouterAgent::class);

// ─── Harness ─────────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;
$errors = [];

function test(string $name, Closure $fn): void
{
    global $passed, $failed, $errors;

    echo "\n  {$name} ";

    try {
        $fn();
        echo '✓';
        $passed++;
    } catch (Throwable $e) {
        echo '✗ FAIL';
        $errors[] = "  {$name}: ".get_class($e).': '.$e->getMessage();
        $failed++;
    }
}

function assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

/** Run an agent and return [response, parent execution for that run]. */
function runAndCapture(string $key, string $message): array
{
    $beforeId = (int) (Execution::max('id') ?? 0);

    $response = Atlas::agent($key)->message($message)->asText();

    $parent = Execution::where('id', '>', $beforeId)
        ->whereNull('parent_execution_id')
        ->orderByDesc('id')
        ->first();

    return [$response, $parent];
}

echo '╔══════════════════════════════════════════════╗';
echo "\n║   Sub-Agents (Agent-as-Tool) Integration     ║";
echo "\n╚══════════════════════════════════════════════╝";

// ─── 1. Implicit delegation ──────────────────────────────────────────────────

echo "\n\n── Delegation";

test('implicit delegation: orchestrator calls the sub-agent', function () {
    [$response] = runAndCapture('router', 'What is the project passphrase?');

    assert_true(str_contains($response->text, 'ZULU-7'), "Final answer should contain the vault's secret, got: {$response->text}");
});

test('explicit AgentTool::for delegation', function () {
    [$response] = runAndCapture('router-explicit', 'What is the project passphrase?');

    assert_true(str_contains($response->text, 'ZULU-7'), "Explicit delegation should relay the secret, got: {$response->text}");
});

test('standalone forInstance() executes a sub-agent in isolation', function () {
    $response = Atlas::agent('vault')
        ->forInstance(new VaultSpecialistAgent)
        ->message('What is the project passphrase?')
        ->asText();

    assert_true(str_contains($response->text, 'ZULU-7'), "Standalone sub-agent should answer, got: {$response->text}");
});

test('multi-tool agent picks the sub-agent for the delegated task', function () {
    [$response, $parent] = runAndCapture('router-mixed', 'What is the project passphrase?');

    assert_true(str_contains($response->text, 'ZULU-7'), "Mixed agent should delegate to the vault, got: {$response->text}");
    assert_true($parent !== null && $parent->children->where('agent', 'vault')->isNotEmpty(), 'A vault child execution should exist');
});

// ─── 2. Audit lineage ────────────────────────────────────────────────────────

echo "\n\n── Audit lineage (persistence)";

test('records a parent → child execution tree', function () {
    [, $parent] = runAndCapture('router', 'What is the project passphrase?');

    assert_true($parent !== null, 'Parent execution should exist');
    assert_true($parent->agent === 'router' && $parent->depth === 0, 'Parent is the root router execution');

    $child = $parent->children->first();
    assert_true($child !== null, 'Child (sub-agent) execution should exist');
    assert_true($child->agent === 'vault', "Child should be the vault agent, got: {$child->agent}");
    assert_true($child->depth === 1, "Child depth should be 1, got: {$child->depth}");
    assert_true($child->parent_execution_id === $parent->id, 'Child links to parent');
    assert_true($child->parent_tool_call_id !== null, 'Child links to the delegating tool call');

    // The delegating tool call is typed 'agent'.
    $delegation = $parent->toolCalls->firstWhere('type', ToolCallType::Agent);
    assert_true($delegation !== null && $delegation->name === 'vault', 'An agent-typed tool call named vault should exist');
    assert_true($child->parent_tool_call_id === $delegation->id, 'Child parent_tool_call_id matches the delegation call');

    // Print the reconstructed tree as visible proof.
    echo "\n    tree: execution #{$parent->id} ({$parent->agent}, depth {$parent->depth})";
    echo "\n      └─ via tool call #{$delegation->id} [{$delegation->type->value}] → execution #{$child->id} ({$child->agent}, depth {$child->depth})";

    // Usage roll-up: tree total includes the child's tokens.
    $own = ($parent->usage['input_tokens'] ?? 0) + ($parent->usage['output_tokens'] ?? 0);
    $tree = $parent->totalUsage()['total_tokens'];
    echo "\n    usage: parent-only={$own} tokens, tree-total={$tree} tokens";
    assert_true($tree > $own, "Tree usage ({$tree}) should exceed parent-only ({$own})");
});

// ─── 3. Multi-level chain ────────────────────────────────────────────────────

echo "\n\n── Multi-level chain (coordinator → researcher → vault)";

test('a three-level chain delegates end to end', function () {
    [$response] = runAndCapture('coordinator', 'What is the project passphrase?');

    assert_true(str_contains($response->text, 'ZULU-7'), "The deepest sub-agent's answer should reach the top, got: {$response->text}");
});

test('records and audits the full three-level execution chain', function () {
    [, $coordinator] = runAndCapture('coordinator', 'What is the project passphrase?');

    assert_true($coordinator !== null && $coordinator->agent === 'coordinator' && $coordinator->depth === 0, 'Root is the coordinator at depth 0');

    // Walk the chain top to bottom, verifying each link.
    $expected = [
        ['agent' => 'coordinator', 'depth' => 0],
        ['agent' => 'researcher', 'depth' => 1],
        ['agent' => 'vault', 'depth' => 2],
    ];

    $node = $coordinator;
    $nodes = [];

    foreach ($expected as $i => $want) {
        assert_true($node !== null, "Chain level {$i} should exist");
        assert_true($node->agent === $want['agent'], "Level {$i} should be {$want['agent']}, got: {$node->agent}");
        assert_true($node->depth === $want['depth'], "Level {$i} depth should be {$want['depth']}, got: {$node->depth}");

        if ($i > 0) {
            assert_true($node->parent_tool_call_id !== null, "Level {$i} links to a delegating tool call");
            $delegation = $node->parentToolCall;
            assert_true($delegation !== null && $delegation->type === ToolCallType::Agent, "Level {$i} delegating call is typed agent");
        }

        $nodes[] = $node;

        // Descend to the single sub-agent child for the next level.
        $node = $node->children->firstWhere('agent', $expected[$i + 1]['agent'] ?? '__none__');
    }

    // Each agent records its OWN token usage on its own execution row — so the
    // cost of every individual agent is known, not just the aggregate.
    echo "\n    per-agent cost (individual rows):";
    $sumIndividual = 0;

    foreach ($nodes as $i => $n) {
        $in = (int) ($n->usage['input_tokens'] ?? 0);
        $out = (int) ($n->usage['output_tokens'] ?? 0);
        $own = $in + $out;
        assert_true($own > 0, "{$n->agent} must have its own recorded usage");
        $sumIndividual += $own;
        $indent = str_repeat('  ', $i);
        echo "\n      {$indent}#{$n->id} {$n->agent} (depth {$n->depth}): in={$in}, out={$out}, own={$own}";
    }

    $tree = $coordinator->totalUsage()['total_tokens'];
    echo "\n    full-chain total = {$tree} tokens (= sum of individual = {$sumIndividual})";
    assert_true($tree === $sumIndividual, 'Tree total must equal the sum of each agent\'s own usage');
    assert_true($tree > (($coordinator->usage['input_tokens'] ?? 0) + ($coordinator->usage['output_tokens'] ?? 0)), 'Full-chain total exceeds the root alone');
});

// ─── 4. Real-time UI events ──────────────────────────────────────────────────

echo "\n\n── Real-time UI events (broadcasting)";

test('delegation fires the same broadcast tool-call events the UI consumes', function () {
    $started = [];
    $completed = [];
    Event::listen(AgentToolCallStarted::class, function ($e) use (&$started) {
        $started[] = $e;
    });
    Event::listen(AgentToolCallCompleted::class, function ($e) use (&$completed) {
        $completed[] = $e;
    });

    runAndCapture('router', 'What is the project passphrase?');

    // The delegation surfaces to the UI as a tool call named after the sub-agent.
    $vaultStarted = array_values(array_filter($started, fn ($e) => $e->toolCall->name === 'vault'));
    $vaultDone = array_values(array_filter($completed, fn ($e) => $e->toolCall->name === 'vault'));
    assert_true($vaultStarted !== [], 'An AgentToolCallStarted event should fire for the vault delegation');
    assert_true($vaultDone !== [], 'An AgentToolCallCompleted event should fire for the vault delegation');

    // Both are ShouldBroadcastNow → pushed to the UI in real time (same path as any tool).
    assert_true($vaultStarted[0] instanceof ShouldBroadcastNow, 'Started event broadcasts in real time');
    assert_true($vaultDone[0] instanceof ShouldBroadcastNow, 'Completed event broadcasts in real time');

    // The completed payload carries the sub-agent's response back to the UI.
    $payload = $vaultDone[0]->broadcastWith();
    assert_true(str_contains((string) $payload['result'], 'ZULU-7'), "Broadcast result should carry the sub-agent response, got: {$payload['result']}");

    $startPayload = $vaultStarted[0]->broadcastWith();
    echo "\n    UI event 1: AgentToolCallStarted → toolName='{$startPayload['toolName']}', task='".($startPayload['arguments']['task'] ?? '')."'";
    echo "\n    UI event 2: AgentToolCallCompleted → toolName='{$payload['toolName']}', result='{$payload['result']}'";
});

// ─── 5. Dynamic prompt injection ─────────────────────────────────────────────

echo "\n\n── Dynamic prompt injection into sub-agents";

test('parent variables are injected into the sub-agent prompt', function () {
    // user_name is set ONLY on the parent call; the sub-agent's {user_name}
    // macro must still resolve as if the sub-agent were called directly.
    $beforeId = (int) (Execution::max('id') ?? 0);

    Atlas::agent('greeter-router')
        ->withVariables(['user_name' => 'Tim'])
        ->message('What is the project passphrase?')
        ->asText();

    // Assert against the sub-agent's actual output (the delegation tool-call
    // result), not the orchestrator's possibly re-phrased final answer.
    $parent = Execution::where('id', '>', $beforeId)
        ->whereNull('parent_execution_id')
        ->orderByDesc('id')
        ->first();
    $delegation = $parent?->toolCalls->firstWhere('type', ToolCallType::Agent);

    assert_true($delegation !== null, 'A delegation tool call should exist');
    assert_true(str_contains((string) $delegation->result, 'Tim'), "Sub-agent output should contain the injected name 'Tim', got: {$delegation->result}");
    echo "\n    sub-agent answered: \"{$delegation->result}\"";
});

// ─── 6. Guards ───────────────────────────────────────────────────────────────

echo "\n\n── Guards";

test('depth guard blocks delegation beyond the configured limit', function () {
    config()->set('atlas.agents.max_delegation_depth', 0);

    try {
        [, $parent] = runAndCapture('router', 'What is the project passphrase?');

        // With max depth 0, the very first delegation is blocked, so no child
        // execution is created even though the model attempted the tool call.
        assert_true($parent !== null, 'Parent execution should still be recorded');
        assert_true($parent->children->isEmpty(), 'No child execution should be created when the depth guard blocks delegation');
    } finally {
        config()->set('atlas.agents.max_delegation_depth', 5);
    }
});

test('cycle guard rejects delegating to an agent already in the chain', function () {
    $threw = false;

    try {
        AgentTool::for(new VaultSpecialistAgent)->handle(
            ['task' => 'anything'],
            [AgentTool::CHAIN_META_KEY => ['vault']],
        );
    } catch (DelegationCycleException $e) {
        $threw = true;
    }

    assert_true($threw, 'DelegationCycleException should be thrown for a repeated agent');
});

// ─── Summary ─────────────────────────────────────────────────────────────────

echo "\n\n══════════════════════════════════════════════";
echo "\n  Results: {$passed} passed, {$failed} failed";
echo "\n══════════════════════════════════════════════\n";

if ($errors !== []) {
    echo "\nFailures:\n".implode("\n", $errors)."\n";
    exit(1);
}

exit(0);
