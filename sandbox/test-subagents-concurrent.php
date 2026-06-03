<?php

declare(strict_types=1);

/**
 * Concurrent Sub-Agents (Parallel Fan-Out) Integration Test — LIVE API
 *
 * Proves — or disproves — that a parent agent can fan out to multiple sub-agents
 * that run AT THE SAME TIME, and that the parent gets ALL of their responses back
 * correctly to continue its own tool loop and synthesize a final answer.
 *
 * Scenario: a `dispatcher` parent delegates to three independent data-source
 * sub-agents (fetch_alpha / fetch_beta / fetch_gamma) in a SINGLE step. Each
 * sub-agent runs a deliberately slow tool (sleeps ~2.5s — a stand-in for a real
 * long-running process / provider call) so wall-clock cleanly separates parallel
 * from sequential execution.
 *
 * The same orchestration runs TWICE — once with concurrent execution OFF, once
 * with it ON — and the harness compares:
 *   • peak simultaneous sub-agents + distinct PIDs, from a per-tool execution
 *     log (microtime|pid|token|START/END) — forking yields different PIDs and
 *     overlapping sleep windows prove genuine parallelism (intra-run, so it is
 *     robust to the model fanning out in a different number of rounds per run)
 *   • correctness: the parent's final answer must carry ALL THREE payload codes
 *     (proof the parent received every sub-agent response and looped on them)
 *   • persistence lineage: depth-1 child executions with correct parent FKs,
 *     across however many parallel rounds the model issued
 *
 * EXPECTED RESULT: concurrent delegation IS implemented (AgentExecutor runs
 * delegation batches through the fork driver and resets DB connections before
 * forking), so the concurrency verdict reads ✓ DETECTED — the concurrent run
 * overlaps multiple sub-agents on distinct forked processes while the sequential
 * run peaks at one. Correctness + lineage pass in both modes.
 *
 * Usage: php test-subagents-concurrent.php
 *
 * Requires OPENAI_API_KEY in sandbox/.env and a migrated database.
 * The fork driver needs the pcntl extension (CLI only).
 */
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\AgentRegistry;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Events\AgentStarted;
use Atlasphp\Atlas\Events\AgentToolCallCompleted;
use Atlasphp\Atlas\Events\AgentToolCallStarted;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
use Atlasphp\Atlas\Persistence\Middleware\PersistConversation;
use Atlasphp\Atlas\Persistence\Middleware\TrackExecution;
use Atlasphp\Atlas\Persistence\Middleware\TrackProviderCall;
use Atlasphp\Atlas\Persistence\Middleware\TrackStep;
use Atlasphp\Atlas\Persistence\Middleware\TrackToolCall;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Schema\Fields\StringField;
use Atlasphp\Atlas\Tools\AgentTool;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Support\Facades\Event;
use Spatie\Fork\Fork;

$app = require __DIR__.'/bootstrap.php';

$app['config']->set('atlas.providers', [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],
]);

$app['config']->set('atlas.persistence.enabled', true);
$app['config']->set('atlas.middleware', [
    PersistConversation::class,
    TrackExecution::class,
    TrackStep::class,
    TrackToolCall::class,
    TrackProviderCall::class,
]);
AtlasConfig::refresh();

// Shared log file each slow tool appends START/END markers to, so we can
// reconstruct execution intervals (and PIDs) across the fork boundary.
const SLOW_TOOL_SLEEP = 2.5;
$logFile = sys_get_temp_dir().'/atlas-concurrent-subagents.log';

// ─── Slow tool: simulates a longer-running process ───────────────────────────

class SlowFetchTool extends Tool
{
    public function __construct(
        protected readonly string $token,
        protected readonly float $seconds,
        protected readonly string $logFile,
    ) {}

    public function name(): string
    {
        return 'slow_fetch';
    }

    public function description(): string
    {
        return 'Fetches the data payload from this source. Takes a few seconds.';
    }

    public function parameters(): array
    {
        return [new StringField('source', 'The source name to fetch from.')];
    }

    public function handle(array $args, array $context): string
    {
        $this->mark('START');
        usleep((int) ($this->seconds * 1_000_000));
        $this->mark('END');

        return "Data payload: {$this->token}";
    }

    private function mark(string $phase): void
    {
        // O_APPEND keeps small writes atomic across concurrent (forked) processes.
        file_put_contents(
            $this->logFile,
            sprintf("%.6f|%d|%s|%s\n", microtime(true), getmypid(), $this->token, $phase),
            FILE_APPEND | LOCK_EX,
        );
    }
}

// ─── Worker sub-agents (one per data source) ─────────────────────────────────

abstract class WorkerAgent extends Agent
{
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

    public function instructions(): ?string
    {
        return 'You fetch data from your assigned source. ALWAYS call the slow_fetch tool '
            .'exactly once, then reply with EXACTLY the payload string it returns — nothing else.';
    }

    abstract protected function token(): string;

    public function tools(): array
    {
        global $logFile;

        return [new SlowFetchTool($this->token(), SLOW_TOOL_SLEEP, $logFile)];
    }
}

class FetchAlphaAgent extends WorkerAgent
{
    public function key(): string
    {
        return 'fetch_alpha';
    }

    public function description(): ?string
    {
        return 'Fetches the data payload from source Alpha.';
    }

    protected function token(): string
    {
        return 'ALPHA-111';
    }
}

class FetchBetaAgent extends WorkerAgent
{
    public function key(): string
    {
        return 'fetch_beta';
    }

    public function description(): ?string
    {
        return 'Fetches the data payload from source Beta.';
    }

    protected function token(): string
    {
        return 'BETA-222';
    }
}

class FetchGammaAgent extends WorkerAgent
{
    public function key(): string
    {
        return 'fetch_gamma';
    }

    public function description(): ?string
    {
        return 'Fetches the data payload from source Gamma.';
    }

    protected function token(): string
    {
        return 'GAMMA-333';
    }
}

// ─── Parent dispatcher: fans out to all three in one step ────────────────────

class DispatcherAgent extends Agent
{
    public function key(): string
    {
        return 'dispatcher';
    }

    public function instructions(): ?string
    {
        return 'You are a data dispatcher with three data-source sub-agents: fetch_alpha, '
            .'fetch_beta, and fetch_gamma. Call ALL THREE sub-agent tools EXACTLY ONCE, '
            .'TOGETHER IN A SINGLE STEP (in parallel) — never one at a time, and never call '
            .'any sub-agent more than once. As soon as all three return, immediately reply '
            .'with ONE line listing the three payload codes, comma-separated, e.g. '
            .'"Codes: X, Y, Z". Do not fetch again to double-check.';
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

    // Default this agent to concurrent execution; the harness still overrides
    // per-run via ->withConcurrent() to A/B the two paths.
    public function concurrent(): bool
    {
        return true;
    }

    public function tools(): array
    {
        return [
            AgentTool::for(new FetchAlphaAgent, 'fetch_alpha', 'Fetch the data payload from source Alpha.'),
            AgentTool::for(new FetchBetaAgent, 'fetch_beta', 'Fetch the data payload from source Beta.'),
            AgentTool::for(new FetchGammaAgent, 'fetch_gamma', 'Fetch the data payload from source Gamma.'),
        ];
    }
}

app(AgentRegistry::class)->register(FetchAlphaAgent::class);
app(AgentRegistry::class)->register(FetchBetaAgent::class);
app(AgentRegistry::class)->register(FetchGammaAgent::class);
app(AgentRegistry::class)->register(DispatcherAgent::class);

// ─── Real-time event capture (what a live UI would broadcast) ─────────────────
//
// Record, per run, the wall-clock ARRIVAL in THIS (parent) process of the events
// a UI broadcasts on. This shows exactly which live updates the UI receives
// during a concurrent batch — and which it does not. $GLOBALS['__mode'] tags each
// captured event with the run it belongs to.

$evt = ['sequential' => [], 'concurrent' => []];
$workerStarted = ['sequential' => 0, 'concurrent' => 0];
$GLOBALS['__mode'] = null;

$record = function (string $kind, string $name) use (&$evt): void {
    $mode = $GLOBALS['__mode'] ?? null;
    if ($mode !== null && in_array($name, ['fetch_alpha', 'fetch_beta', 'fetch_gamma'], true)) {
        $evt[$mode][] = ['kind' => $kind, 't' => microtime(true), 'name' => $name];
    }
};

Event::listen(AgentToolCallStarted::class, fn ($e) => $record('started', $e->toolCall->name));
Event::listen(AgentToolCallCompleted::class, fn ($e) => $record('completed', $e->toolCall->name));

// A sub-agent's OWN AgentStarted fires in-process under sequential delegation,
// but inside the forked child under concurrency — so it should be seen here for
// sequential and NOT for concurrent. This pins the fork event boundary.
Event::listen(AgentStarted::class, function ($e) use (&$workerStarted): void {
    $mode = $GLOBALS['__mode'] ?? null;
    if ($mode !== null && in_array($e->agentKey, ['fetch_alpha', 'fetch_beta', 'fetch_gamma'], true)) {
        $workerStarted[$mode]++;
    }
});

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

$expectedCodes = ['ALPHA-111', 'BETA-222', 'GAMMA-333'];

/**
 * Run the dispatcher once in the given mode and return a structured result:
 * wall-clock ms, final text, the parent Execution, and the parsed tool log.
 *
 * @return array{wallMs: float, text: string, parent: ?Execution, intervals: array<string, array{start: float, end: float, pid: int}>, pids: array<int, int>}
 */
function runDispatch(bool $concurrent, string $logFile): array
{
    // Fresh log per run so interval parsing only sees this run's markers.
    file_put_contents($logFile, '');

    $beforeId = (int) (Execution::max('id') ?? 0);

    $start = microtime(true);

    $response = Atlas::agent('dispatcher')
        ->withConcurrent($concurrent)
        ->message('Fetch the data payloads from all three sources and give me their codes.')
        ->asText();

    $wallMs = (microtime(true) - $start) * 1000;

    $parent = Execution::where('id', '>', $beforeId)
        ->whereNull('parent_execution_id')
        ->orderByDesc('id')
        ->first();

    return [
        'wallMs' => $wallMs,
        'text' => $response->text,
        'parent' => $parent,
        ...parseToolLog($logFile),
    ];
}

/**
 * Parse the slow-tool log into per-token [start,end,pid] intervals + the set of
 * distinct PIDs that ran them.
 *
 * @return array{intervals: array<string, array{start: float, end: float, pid: int}>, pids: array<int, int>}
 */
function parseToolLog(string $logFile): array
{
    $intervals = [];
    $pids = [];

    foreach (file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        [$ts, $pid, $token, $phase] = explode('|', $line);
        $ts = (float) $ts;
        $pid = (int) $pid;
        $pids[$pid] = $pid;

        if ($phase === 'START') {
            $intervals[$token]['start'] = $ts;
            $intervals[$token]['pid'] = $pid;
        } else {
            $intervals[$token]['end'] = $ts;
        }
    }

    return ['intervals' => $intervals, 'pids' => array_values($pids)];
}

/**
 * Peak number of slow-tool intervals active at the same instant — the hard proof
 * of parallelism, and robust to the model fanning out across multiple rounds.
 * Sequential execution peaks at 1; genuine parallel fan-out peaks at the batch
 * size (3 here). Computed with a sweep line over interval start/end events.
 *
 * @param  array<string, array{start: float, end: float, pid: int}>  $intervals
 */
function peakConcurrency(array $intervals): int
{
    $events = [];

    foreach ($intervals as $i) {
        if (! isset($i['start'], $i['end'])) {
            continue;
        }
        $events[] = [$i['start'], +1];
        $events[] = [$i['end'], -1];
    }

    // Sort by time; on ties, process ENDs (-1) before STARTs (+1) so touching
    // intervals are not miscounted as overlapping.
    usort($events, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

    $active = 0;
    $peak = 0;

    foreach ($events as [, $delta]) {
        $active += $delta;
        $peak = max($peak, $active);
    }

    return $peak;
}

echo '╔══════════════════════════════════════════════╗';
echo "\n║   Concurrent Sub-Agents (Parallel Fan-Out)   ║";
echo "\n╚══════════════════════════════════════════════╝";

echo "\n\nEnvironment:";
echo "\n  pcntl extension : ".(extension_loaded('pcntl') ? 'loaded' : 'MISSING (fork → sync fallback)');
echo "\n  spatie/fork     : ".(class_exists(Fork::class) ? 'available' : 'MISSING');
echo "\n  per-tool sleep  : ".SLOW_TOOL_SLEEP.'s × 3 sub-agents';
echo "\n  expectation     : sequential ≈ ".(SLOW_TOOL_SLEEP * 3).'s, parallel ≈ '.SLOW_TOOL_SLEEP."s\n";

// ─── Run both modes ──────────────────────────────────────────────────────────

$GLOBALS['__mode'] = 'sequential';
echo "\n── Running SEQUENTIAL (concurrent: false) …";
$seq = runDispatch(false, $logFile);
echo ' done in '.round($seq['wallMs']).'ms';

$GLOBALS['__mode'] = 'concurrent';
echo "\n── Running CONCURRENT (concurrent: true) …";
$con = runDispatch(true, $logFile);
echo ' done in '.round($con['wallMs']).'ms';
$GLOBALS['__mode'] = null;

// ─── Correctness: the parent got every sub-agent response back ───────────────

echo "\n\n── Correctness: parent receives ALL sub-agent responses";

foreach (['sequential' => $seq, 'concurrent' => $con] as $mode => $run) {
    test("[{$mode}] final answer carries all three payload codes", function () use ($run, $expectedCodes, $mode) {
        foreach ($expectedCodes as $code) {
            assert_true(
                str_contains($run['text'], $code),
                "[{$mode}] parent's final answer must contain {$code} (proves the response looped back); got: {$run['text']}",
            );
        }
    });
}

echo "\n    sequential final: \"".trim($seq['text']).'"';
echo "\n    concurrent final: \"".trim($con['text']).'"';

// ─── Lineage: three depth-1 children with correct parent FKs ─────────────────

echo "\n\n── Persistence lineage (parent → 3 children)";

foreach (['sequential' => $seq, 'concurrent' => $con] as $mode => $run) {
    test("[{$mode}] records correct parent → child lineage for every delegation", function () use ($run, $mode) {
        $parent = $run['parent'];
        assert_true($parent !== null && $parent->agent === 'dispatcher' && $parent->depth === 0, "[{$mode}] root dispatcher execution at depth 0");

        // The model may fan out in one or more rounds (it is nondeterministic) —
        // each round is a parallel batch of all three. Assert the structure holds
        // regardless of how many rounds it chose: a positive multiple of three,
        // every child depth-1 with correct parent FKs.
        $children = $parent->children;
        assert_true(
            $children->count() >= 3 && $children->count() % 3 === 0,
            "[{$mode}] expected a positive multiple of 3 child executions (rounds × 3), got {$children->count()}",
        );

        foreach ($children as $child) {
            assert_true($child->depth === 1, "[{$mode}] child depth should be 1, got {$child->depth}");
            assert_true($child->parent_execution_id === $parent->id, "[{$mode}] child links to parent execution");
            assert_true($child->parent_tool_call_id !== null, "[{$mode}] child links to its delegating tool call");
        }

        // Each delegation round must be issued as ONE batched step fanning out to
        // all three agents — that batching is what the executor parallelises.
        $delegations = $parent->toolCalls->where('type', ToolCallType::Agent);
        $byStep = $delegations->groupBy('step_id');

        foreach ($byStep as $stepId => $calls) {
            $names = $calls->pluck('name')->unique()->sort()->values()->all();
            assert_true(
                $names === ['fetch_alpha', 'fetch_beta', 'fetch_gamma'],
                "[{$mode}] each delegation step must fan out to all three agents, step {$stepId} had: ".implode(',', $names),
            );
        }

        $rounds = $byStep->count();
        echo "\n    [{$mode}] {$rounds} parallel round(s) of 3 delegations, ".$children->count().' child executions, all depth-1 with correct FKs ✓';
    });
}

// ─── Concurrency verdict ─────────────────────────────────────────────────────

echo "\n\n── Concurrency verdict";

// Peak simultaneous sub-agent activity is the hard, intra-run proof. Cross-run
// wall-clock is unreliable on its own because the model is free to fan out in a
// different number of rounds per run — so it is reported as context only.
$seqPeak = peakConcurrency($seq['intervals']);
$conPeak = peakConcurrency($con['intervals']);

echo "\n    peak simultaneous sub-agents : sequential={$seqPeak}  concurrent={$conPeak}  (sequential→1, parallel→up to 3)";
echo "\n    distinct PIDs                : sequential=".count($seq['pids']).'  concurrent='.count($con['pids']).'  (fork → >1)';
echo "\n    wall-clock (context only)    : sequential=".round($seq['wallMs']).'ms  concurrent='.round($con['wallMs']).'ms';

// Detected when the concurrent run genuinely overlapped ≥2 sub-agents in time on
// distinct processes, while the sequential run did not.
$concurrencyDetected = $conPeak >= 2 && count($con['pids']) > 1 && $seqPeak === 1;

test('concurrent execution overlaps multiple sub-agents; sequential does not', function () use ($conPeak, $seqPeak, $con) {
    assert_true($seqPeak === 1, "sequential run must NOT overlap (peak={$seqPeak}, expected 1)");
    assert_true($conPeak >= 2, "concurrent run must overlap ≥2 sub-agents (peak={$conPeak})");
    assert_true(count($con['pids']) > 1, 'concurrent run must fork into multiple processes');
});

echo "\n\n    ┌────────────────────────────────────────────────┐";
echo "\n    │  CONCURRENT SUB-AGENT EXECUTION: ".str_pad($concurrencyDetected ? '✓ DETECTED' : '❌ NOT DETECTED', 14).'│';
echo "\n    └────────────────────────────────────────────────┘";

if ($concurrencyDetected) {
    echo "\n    → {$conPeak} sub-agents ran AT THE SAME TIME across ".count($con['pids']).' forked processes,';
    echo "\n      every response returned to the parent, and the parent looped to a";
    echo "\n      correct final answer. Lineage stayed intact across the fork boundary.";
} else {
    echo "\n    → Sub-agents did NOT run in parallel. If pcntl/spatie-fork are present,";
    echo "\n      check that AgentExecutor allows delegation batches through the";
    echo "\n      concurrent path and resets DB connections before forking.";
}

// ─── Real-time broadcasting to the UI ────────────────────────────────────────
//
// What live updates does a UI actually receive during a concurrent batch?

/**
 * Summarise the timing of captured tool-call events for one run.
 *
 * @param  array<int, array{kind: string, t: float, name: string}>  $events
 * @return array{started: int, completed: int, startedSpreadMs: float, completedSpreadMs: float, silentGapMs: float}
 */
function eventTimeline(array $events): array
{
    $st = array_column(array_filter($events, fn ($e) => $e['kind'] === 'started'), 't');
    $ct = array_column(array_filter($events, fn ($e) => $e['kind'] === 'completed'), 't');

    return [
        'started' => count($st),
        'completed' => count($ct),
        // Spread within each cluster: small = all fired together.
        'startedSpreadMs' => $st !== [] ? (max($st) - min($st)) * 1000 : 0.0,
        'completedSpreadMs' => $ct !== [] ? (max($ct) - min($ct)) * 1000 : 0.0,
        // Window between the last "started" and the first "completed". A large
        // positive gap = the UI received NO updates while the tools ran (batched
        // at the fork join). Negative = completions interleaved with starts
        // (sequential streams each tool's progress in real time).
        'silentGapMs' => ($st !== [] && $ct !== []) ? (min($ct) - max($st)) * 1000 : 0.0,
    ];
}

$seqTl = eventTimeline($evt['sequential']);
$conTl = eventTimeline($evt['concurrent']);

echo "\n\n── Real-time broadcasting to the UI (live events the parent emits)";
echo "\n    SEQUENTIAL: started spread=".round($seqTl['startedSpreadMs']).'ms, completed spread='.round($seqTl['completedSpreadMs']).'ms, start→complete gap='.round($seqTl['silentGapMs']).'ms';
echo "\n                → each tool starts, runs, and completes in turn — fully streamed, one at a time";
echo "\n    CONCURRENT: started spread=".round($conTl['startedSpreadMs']).'ms, completed spread='.round($conTl['completedSpreadMs']).'ms, start→complete gap='.round($conTl['silentGapMs']).'ms';
echo "\n                → all 3 'started' broadcast together (UI shows 3 in progress at once),";
echo "\n                  then a silent window while they run, then all 3 'completed' together at the join";
echo "\n    sub-agent INTERNAL events delivered in-process: sequential={$workerStarted['sequential']}, concurrent={$workerStarted['concurrent']}";
echo "\n                → 0 for concurrent confirms a sub-agent's own steps fire inside the fork (not broadcast live)";

test('concurrent batch broadcasts all three tool-starts up front (UI can show 3 in progress at once)', function () use ($conTl) {
    assert_true($conTl['started'] === 3, "expected 3 'started' events, got {$conTl['started']}");
    assert_true($conTl['startedSpreadMs'] < 500, "all three 'started' should broadcast together, spread was {$conTl['startedSpreadMs']}ms");
});

test('concurrent tool completions arrive batched at the join, not streamed per tool', function () use ($conTl) {
    assert_true($conTl['completed'] === 3, "expected 3 'completed' events, got {$conTl['completed']}");
    assert_true($conTl['silentGapMs'] > 1500, "expected a silent window (~the parallel work) with no events, gap was {$conTl['silentGapMs']}ms");
    assert_true($conTl['completedSpreadMs'] < 500, "the three 'completed' should arrive together at the join, spread was {$conTl['completedSpreadMs']}ms");
});

test("sub-agents' own internal events stream in-process sequentially but NOT across the fork", function () use ($workerStarted) {
    assert_true($workerStarted['sequential'] >= 3, "sequential should deliver worker AgentStarted in-process, got {$workerStarted['sequential']}");
    assert_true($workerStarted['concurrent'] === 0, "concurrent worker AgentStarted fire inside the fork and must NOT reach the parent, got {$workerStarted['concurrent']}");
});

// ─── Post-completion drill-down (what the UI shows when you open a call) ──────
//
// Live streaming of a concurrent sub-agent's internals is not delivered to the
// parent (above). But its FULL result IS persisted from inside the fork. This is
// exactly what a UI does when you click a COMPLETED delegation tool call: load
// the sub-agent's response, the steps it took (with their text), and the tools
// it ran (with arguments, results, and timing). Reconstructed from the database
// for the concurrent run below — proving full after-the-fact visibility.

echo "\n\n── Post-completion drill-down (concurrent run — full sub-agent visibility)";

test('every concurrent sub-agent exposes its response, steps, and tools after completion', function () use ($con) {
    $parent = $con['parent'];
    $delegations = $parent->toolCalls->where('type', ToolCallType::Agent);
    assert_true($delegations->isNotEmpty(), 'parent should have delegation tool calls to drill into');

    foreach ($delegations as $tc) {
        // 1. The sub-agent's RESPONSE — on the delegation tool call result.
        assert_true(is_string($tc->result) && $tc->result !== '', "delegation '{$tc->name}' must carry the sub-agent response");

        // 2. The linked child execution is fully recorded.
        $child = Execution::where('parent_tool_call_id', $tc->id)->first();
        assert_true($child !== null, "delegation '{$tc->name}' must link to a child execution");
        assert_true($child->status === ExecutionStatus::Completed, "child '{$child->agent}' should be completed");
        assert_true(($child->usage['output_tokens'] ?? 0) > 0, "child '{$child->agent}' should have recorded usage");

        // 3. The sub-agent's STEPS — recorded with their text content.
        $steps = $child->steps()->orderBy('sequence')->get();
        assert_true($steps->isNotEmpty(), "child '{$child->agent}' should have recorded steps");
        assert_true(
            $steps->contains(fn ($s) => is_string($s->content) && $s->content !== ''),
            "child '{$child->agent}' steps should include the response text",
        );

        // 4. The TOOLS the sub-agent ran — with arguments, result, and timing.
        $ranTools = $child->toolCalls()->where('type', ToolCallType::Local)->get();
        assert_true($ranTools->isNotEmpty(), "child '{$child->agent}' should record the tools it ran (slow_fetch)");
        foreach ($ranTools as $ct) {
            assert_true(is_string($ct->result) && $ct->result !== '', "tool '{$ct->name}' on '{$child->agent}' should record its result");
            assert_true($ct->duration_ms !== null, "tool '{$ct->name}' on '{$child->agent}' should record its duration");
        }
    }
});

// Render the tree a UI would display when drilling into the concurrent run.
foreach ($con['parent']->toolCalls->where('type', ToolCallType::Agent) as $tc) {
    $child = Execution::where('parent_tool_call_id', $tc->id)->first();
    echo "\n    ▸ tool call '{$tc->name}' → response: \"".trim((string) $tc->result).'"';

    if ($child === null) {
        continue;
    }

    $tokens = ($child->usage['input_tokens'] ?? 0).'in/'.($child->usage['output_tokens'] ?? 0).'out';
    echo "\n        └ sub-agent execution #{$child->id} ({$child->agent}, {$child->status->value}, {$tokens})";

    foreach ($child->steps()->orderBy('sequence')->get() as $s) {
        $txt = ($s->content !== null && $s->content !== '') ? '"'.trim((string) $s->content).'"' : '(tool-call step)';
        echo "\n            step {$s->sequence} [{$s->finish_reason}]: {$txt}";
    }

    foreach ($child->toolCalls()->where('type', ToolCallType::Local)->get() as $ct) {
        echo "\n            ran tool {$ct->name}(".json_encode($ct->arguments).') → "'.trim((string) $ct->result)."\" ({$ct->duration_ms}ms)";
    }
}
echo "\n    → full sub-agent responses, per-step text, and the tools they ran are all queryable after a concurrent run ✓";

// ─── Summary ─────────────────────────────────────────────────────────────────

echo "\n\n══════════════════════════════════════════════";
echo "\n  Results: {$passed} passed, {$failed} failed";
echo "\n  Concurrency: ".($concurrencyDetected ? 'DETECTED ✓' : 'not yet (baseline)');
echo "\n══════════════════════════════════════════════\n";

if ($errors !== []) {
    echo "\nFailures:\n".implode("\n", $errors)."\n";
    exit(1);
}

exit(0);
