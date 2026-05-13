<?php

declare(strict_types=1);

/**
 * Embeddings — Full Suite
 *
 * One end-to-end run that exercises every embedding tier Atlas exposes
 * against the real OpenAI API and a live PostgreSQL + pgvector database:
 *
 *   1. Raw single embed                     Atlas::embed()->fromInput($string)
 *   2. Raw batch embed                      Atlas::embed()->fromInput([...])
 *   3. Whole-record embeddings + search     HasVectorEmbeddings + Note model
 *   4. Chunked embeddings + search          HasChunkedEmbeddings + Project model
 *   5. Agent-tool experience                SimilaritySearch::usingModel(...)
 *   6. ids filter (whole-record + chunked)  Atlas::similaritySearch(..., ['ids' => ...])
 *   7. JSON shape verification              SearchResult::jsonSerialize() trim
 *   8. Latency baseline                     wall-clock per query (caching off)
 *
 * Each section prints the inputs, the results with id + similarity + content
 * preview, and asserts against expected outcomes. Designed to read like a
 * walkthrough — open the output and you can see exactly what an agent or
 * consumer would experience at each tier.
 *
 * Usage:
 *   cd sandbox
 *   php artisan migrate         # one-time
 *   php test-embeddings-full-suite.php
 *
 * Requires OPENAI_API_KEY in sandbox/.env and PostgreSQL with pgvector.
 */
$app = require __DIR__.'/bootstrap.php';

use App\Models\Note;
use App\Models\Project;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\SearchResult;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Persistence\Services\ChunkContentService;
use Atlasphp\Atlas\Tools\SimilaritySearch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app['config']->set('atlas.defaults.embed', [
    'provider' => 'openai',
    'model' => env('ATLAS_EMBED_MODEL', 'text-embedding-3-small'),
]);
$app['config']->set('atlas.providers.openai', [
    'api_key' => env('OPENAI_API_KEY'),
    'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
]);
// Bypass embedding cache so latency numbers reflect real API round trips.
$app['config']->set('atlas.cache.ttl.embeddings', 0);
// Smaller chunk size so the architecture-heavy section produces multiple
// chunks (otherwise one chunk holds the whole section and per-fact retrieval
// is muddied).
$app['config']->set('atlas.embeddings.chunk_size', 150);
$app['config']->set('atlas.embeddings.chunk_overlap', 30);
AtlasConfig::refresh();

if (DB::connection()->getDriverName() !== 'pgsql') {
    echo "This suite requires PostgreSQL (DB_CONNECTION=pgsql). Aborting.\n";
    exit(1);
}

if (! Schema::hasTable('projects') || ! Schema::hasTable('notes') || ! Schema::hasTable('atlas_chunks')) {
    echo "Missing tables — run `php artisan migrate` first.\n";
    exit(1);
}

DB::table('atlas_chunks')->delete();
DB::table('projects')->delete();
DB::table('notes')->delete();

// ─── Output helpers ─────────────────────────────────────────────────────────

$failures = [];

function section(string $title): void
{
    echo "\n".str_repeat('═', 76)."\n  {$title}\n".str_repeat('═', 76)."\n";
}

function subsection(string $title): void
{
    echo "\n".str_repeat('─', 76)."\n  {$title}\n".str_repeat('─', 76)."\n";
}

function preview(?string $text, int $width = 100): string
{
    if ($text === null) {
        return '(null)';
    }
    $clean = preg_replace('/\s+/', ' ', trim($text)) ?? '';

    return mb_strlen($clean) <= $width ? $clean : mb_substr($clean, 0, $width - 1).'…';
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    $mark = $ok ? '✓' : '✗';
    echo "  {$mark} {$name}".($detail !== '' ? " — {$detail}" : '')."\n";
    if (! $ok) {
        $failures[] = $name.($detail !== '' ? ": {$detail}" : '');
    }
}

function timed(callable $fn): array
{
    $start = microtime(true);
    $result = $fn();
    $elapsedMs = (microtime(true) - $start) * 1000;

    return [$result, $elapsedMs];
}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 1 — Raw single embed
// ═══════════════════════════════════════════════════════════════════════════

section('1. Raw single embed — Atlas::embed()->fromInput($string)');

[$response, $ms] = timed(fn () => Atlas::embed()
    ->fromInput('What is Laravel and why do PHP developers love it?')
    ->asEmbeddings());

printf("  Wall-clock: %.0f ms\n", $ms);
echo '  Vectors returned: '.count($response->embeddings)."\n";
echo '  Vector dimensions: '.count($response->embeddings[0])."\n";
echo '  Tokens: in='.$response->usage->inputTokens.' out='.$response->usage->outputTokens."\n";
echo '  First 5 components: ['.implode(', ', array_map(fn ($v) => sprintf('%.4f', $v), array_slice($response->embeddings[0], 0, 5)))."…]\n";

check('exactly one vector returned', count($response->embeddings) === 1);
check('vector has 1536 dimensions (text-embedding-3-small default)', count($response->embeddings[0]) === 1536);
check('vector has non-zero components', array_sum(array_map('abs', array_slice($response->embeddings[0], 0, 10))) > 0.01);
check('latency under 2 seconds', $ms < 2000, sprintf('took %.0f ms', $ms));

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 2 — Raw batch embed
// ═══════════════════════════════════════════════════════════════════════════

section('2. Raw batch embed — Atlas::embed()->fromInput([...])');

$batch = [
    'PHP is a server-side scripting language for web development.',
    'Laravel is the most popular PHP framework, known for elegant syntax.',
    'Eloquent is Laravel\'s ORM for working with database records.',
    'Pgvector is a PostgreSQL extension that adds vector similarity search.',
];

[$response, $ms] = timed(fn () => Atlas::embed()->fromInput($batch)->asEmbeddings());

printf("  Wall-clock: %.0f ms (%d inputs)\n", $ms, count($batch));
echo '  Vectors returned: '.count($response->embeddings)."\n";
echo '  Per-vector latency (avg): '.sprintf('%.0f', $ms / count($batch))." ms\n";
echo '  Tokens: in='.$response->usage->inputTokens."\n";

check('returns one vector per input', count($response->embeddings) === count($batch));
check('every vector is 1536 dimensions',
    count(array_filter($response->embeddings, fn ($v) => count($v) === 1536)) === count($batch));
check('batch latency beats sequential (< 4× single)', $ms < 8000, sprintf('took %.0f ms', $ms));

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 3 — Whole-record embeddings (HasVectorEmbeddings)
// ═══════════════════════════════════════════════════════════════════════════

section('3. Whole-record embeddings — Note model with HasVectorEmbeddings');

subsection('3a. Auto-embed on save');

$noteSeed = [
    ['title' => 'On-call escalation', 'body' => 'Pages escalate to engineering manager at the thirty-minute mark when customer impact is confirmed.'],
    ['title' => 'Database recovery', 'body' => 'Never run a recovery script in production without a witness on the call to confirm the command.'],
    ['title' => 'Release timing', 'body' => 'Public GA targets December. Holiday freeze blocks any changes between December 15 and January 5.'],
    ['title' => 'Stakeholders', 'body' => 'Anna Marquez owns product roadmap. Brian leads engineering. Cara handles customer enablement.'],
    ['title' => 'Pagination usage', 'body' => 'Iterate cursor-based pages with $client->users()->iterate() — emits each record without holding the whole set in memory.'],
    ['title' => 'Idempotency strategy', 'body' => 'Tag each invoice with a deterministic hash of its content plus the source identifier; duplicates resolve to the same event id.'],
];

$saveStart = microtime(true);
foreach ($noteSeed as $row) {
    Note::create($row);
}
$totalSaveMs = (microtime(true) - $saveStart) * 1000;
printf("  Saved %d notes in %.0f ms (%.0f ms/note avg, includes embedding API call per save)\n",
    count($noteSeed), $totalSaveMs, $totalSaveMs / count($noteSeed));

check('all notes have populated embedding column', Note::query()->whereNotNull('embedding')->count() === count($noteSeed));
check('all notes have embedding_at timestamp', Note::query()->whereNotNull('embedding_at')->count() === count($noteSeed));

subsection('3b. Whole-record similarity search via Atlas::similaritySearch()');

$noteQueries = [
    ['q' => 'who escalates customer issues', 'expected_id_for_title' => 'On-call escalation'],
    ['q' => 'who owns the product roadmap', 'expected_id_for_title' => 'Stakeholders'],
    ['q' => 'how do we handle database recovery', 'expected_id_for_title' => 'Database recovery'],
    ['q' => 'when does the holiday code freeze begin', 'expected_id_for_title' => 'Release timing'],
];

foreach ($noteQueries as $q) {
    [$results, $ms] = timed(fn () => Atlas::similaritySearch(Note::class, $q['q'], ['limit' => 3]));

    printf("\n  Query: \"%s\"  (%.0f ms)\n", $q['q'], $ms);
    foreach ($results as $i => $r) {
        printf("    %d. id=%-3d sim=%.4f  \"%s\"\n", $i + 1, $r->record->id, $r->similarity, $r->record->title);
        printf("       %s\n", preview($r->content));
    }

    $top = $results->first();
    $expectedId = Note::where('title', $q['expected_id_for_title'])->value('id');
    check('  → top result matches expected ('.$q['expected_id_for_title'].')',
        $top->record->id === $expectedId,
        "got id={$top->record->id} title=\"{$top->record->title}\"");
    check('  → top similarity > 0.3 (semantic match found)',
        $top->similarity > 0.3,
        sprintf('similarity=%.4f', $top->similarity));
    check('  → record exposed (id + title accessible)',
        is_int($top->record->id) && is_string($top->record->title));
    check('  → search latency < 1 second', $ms < 1000, sprintf('took %.0f ms', $ms));
}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 4 — Chunked embeddings (HasChunkedEmbeddings)
// ═══════════════════════════════════════════════════════════════════════════

section('4. Chunked embeddings — Project model with HasChunkedEmbeddings');

subsection('4a. Reconcile a long-form markdown body');

$projectBody = <<<'MD'
# Project Overview

Invoice ingestion for the regional finance team. Replaces a brittle spreadsheet workflow with a typed pipeline backed by the new billing service.

## Stakeholders

Anna Marquez owns the product roadmap and approves scope. Brian leads engineering. Cara handles customer enablement and writes release notes.

### Engineering team

Brian leads a four-person team. Pri owns the ingestion service. Devon owns the projection layer. Maria is the SRE embedded for the duration of the project.

## Risks

### Data quality risk

Roughly fifteen percent of incoming invoices fail validation today and we will inherit that floor. Triage workflow needed before launch.

### Integration timing risk

The billing rewrite is fragile. If billing slips by more than four weeks our beta window collapses.

## Notes on architecture

The pipeline uses an event sourcing pattern. Each ingested invoice produces an immutable event; a projection rebuilds the queryable state. Rebuild any view from raw events if a bug corrupts state.

The ingestion service shards events by tenant. A single noisy customer cannot starve others. Per-tenant streams cost cross-tenant analytics complexity but buy clean backpressure.

For idempotency, tag every invoice with a deterministic hash of its content plus a stable source identifier. Duplicate submissions resolve to the same event id and are dropped silently at the ingestion boundary.

Failure handling follows a circuit-breaker pattern. After three consecutive billing-API health-check failures within sixty seconds, the breaker opens and ingestion buffers events to a quarantine queue. Once health is restored, quarantined events drain in their original order.
MD;

$alphaProject = Project::create(['title' => 'Invoice Ingestion Brief', 'body' => $projectBody]);

[$_, $reconcileMs] = timed(fn () => app(ChunkContentService::class)->reconcile($alphaProject));
$alphaProject->refresh();

printf("  Reconcile: %.0f ms (chunked + embedded inline)\n", $reconcileMs);
printf("  Chunks generated: %d  body length: %d chars\n",
    Chunk::where('chunkable_id', $alphaProject->id)->count(),
    strlen($projectBody));

check('all chunks have non-null embedding column',
    Chunk::where('chunkable_id', $alphaProject->id)->whereNotNull('embedding')->count() ===
    Chunk::where('chunkable_id', $alphaProject->id)->count());
check('content_hash equals indexed_hash after reconcile',
    $alphaProject->content_hash === $alphaProject->indexed_hash);
check('chunks carry heading_path attribution',
    Chunk::where('chunkable_id', $alphaProject->id)->whereNotNull('heading_path')->count() > 0);

subsection('4b. Chunked similarity search');

$chunkQueries = [
    ['q' => 'who runs the ingestion service', 'mustContain' => 'Pri'],
    // Match either "idempotency" (header chunk) or "Duplicate submissions" /
    // "deterministic hash" (body chunk) — chunk_size=150 splits this paragraph
    // and the body chunk wins the semantic match for "replay storms" (which is
    // correct: dedup IS how replay storms are handled).
    ['q' => 'how does the system handle replay storms', 'mustContain' => 'Duplicate'],
    ['q' => 'how does the circuit breaker behave', 'mustContain' => 'circuit-breaker'],
    ['q' => 'what is the data quality risk', 'mustContain' => 'fifteen percent'],
];

foreach ($chunkQueries as $q) {
    [$results, $ms] = timed(fn () => Atlas::similaritySearch(Project::class, $q['q'], ['limit' => 3]));

    printf("\n  Query: \"%s\"  (%.0f ms)\n", $q['q'], $ms);
    foreach ($results as $i => $r) {
        printf("    %d. id=%-3d ord=%-2d sim=%.4f  heading: %s\n",
            $i + 1, $r->record->id, $r->ord, $r->similarity, $r->headingPath ?? '—');
        printf("       %s\n", preview($r->content));
    }

    $top = $results->first();
    check('  → top result content contains "'.$q['mustContain'].'"',
        stripos($top->content, $q['mustContain']) !== false,
        'got: '.preview($top->content, 70));
    check('  → top has hydrated parent + ord + headingPath',
        $top->record instanceof Project && is_int($top->ord) && is_string($top->headingPath));
    check('  → search latency < 1 second', $ms < 1000, sprintf('took %.0f ms', $ms));
}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 5 — Agent-tool experience
// ═══════════════════════════════════════════════════════════════════════════

section('5. Agent-tool experience — SimilaritySearch::usingModel(...)');

subsection('5a. Tool wired against Note (whole-record mode)');

$noteTool = SimilaritySearch::usingModel(Note::class, limit: 3)
    ->withName('search_notes')
    ->withDescription('Search engineering notes by semantic similarity.');

[$noteToolResults, $ms] = timed(fn () => $noteTool->handle(['query' => 'when does the freeze start'], []));

printf("  Tool returned %d results in %.0f ms\n", $noteToolResults->count(), $ms);
foreach ($noteToolResults as $i => $r) {
    printf("    %d. id=%-3d sim=%.4f  \"%s\"\n", $i + 1, $r->record->id, $r->similarity, $r->record->title);
}

check('tool returned a Collection of SearchResult', $noteToolResults instanceof Collection
    && $noteToolResults->isNotEmpty()
    && $noteToolResults->first() instanceof SearchResult);

subsection('5b. Tool wired against Project (chunked mode) — same shape, different mode');

$projectTool = SimilaritySearch::usingModel(Project::class, limit: 3)
    ->withName('search_projects')
    ->withDescription('Search project briefs by semantic similarity.');

[$projectToolResults, $ms] = timed(fn () => $projectTool->handle(['query' => 'who handles customer enablement'], []));

printf("  Tool returned %d results in %.0f ms\n", $projectToolResults->count(), $ms);
foreach ($projectToolResults as $i => $r) {
    printf("    %d. id=%-3d ord=%-2d sim=%.4f  heading: %s\n",
        $i + 1, $r->record->id, $r->ord, $r->similarity, $r->headingPath ?? '—');
}

check('tool dispatched chunked mode (heading_path populated)',
    $projectToolResults->first()->headingPath !== null);
check('top result mentions Cara (the right person)',
    stripos($projectToolResults->first()->content, 'Cara') !== false);

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 6 — JSON shape (what the LLM actually sees)
// ═══════════════════════════════════════════════════════════════════════════

section('6. JSON shape verification — agent payload size + structure');

$jsonPayload = $projectToolResults->toJson();
$decoded = json_decode($jsonPayload, true);

printf("  JSON payload size: %d bytes (%.1f KB)\n", strlen($jsonPayload), strlen($jsonPayload) / 1024);
printf("  Top result JSON shape:\n");
echo '    '.json_encode($decoded[0] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

check('payload omits the full Eloquent record (no body field)',
    ! str_contains($jsonPayload, 'event sourcing pattern')
    && ! str_contains($jsonPayload, 'circuit-breaker pattern'));
check('each result exposes id + type for follow-up lookups',
    isset($decoded[0]['id']) && isset($decoded[0]['type']));
check('each result exposes content + similarity for ranking',
    isset($decoded[0]['content']) && isset($decoded[0]['similarity']));
check('each result exposes heading_path + ord for chunked context',
    array_key_exists('heading_path', $decoded[0]) && array_key_exists('ord', $decoded[0]));
check('payload size sane for LLM context (< 4KB for 3 results)',
    strlen($jsonPayload) < 4096,
    sprintf('actual=%d bytes', strlen($jsonPayload)));

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 7 — ids filter (whole-record + chunked)
// ═══════════════════════════════════════════════════════════════════════════

section('7. ids filter — scope search to specific records');

subsection('7a. Whole-record: search ids = single int');

$pickNoteId = Note::where('title', 'Stakeholders')->value('id');
[$idsResults, $ms] = timed(fn () => Atlas::similaritySearch(Note::class, 'who handles enablement', [
    'limit' => 5,
    'ids' => $pickNoteId,
]));
printf("  ids => %d (single int) — got %d result(s) in %.0f ms\n", $pickNoteId, $idsResults->count(), $ms);
foreach ($idsResults as $r) {
    printf("    id=%d sim=%.4f \"%s\"\n", $r->record->id, $r->similarity, $r->record->title);
}
check('returns exactly 1 result (the scoped id)', $idsResults->count() === 1);
check('the result is the scoped id', $idsResults->first()->record->id === $pickNoteId);

subsection('7b. Whole-record: search ids = [a, b, c]');

$pickIds = Note::whereIn('title', ['Stakeholders', 'Release timing', 'Pagination usage'])->pluck('id')->all();
[$multiResults, $ms] = timed(fn () => Atlas::similaritySearch(Note::class, 'engineering team and timing', [
    'limit' => 10,
    'ids' => $pickIds,
]));
printf("  ids => %s — got %d result(s) in %.0f ms\n", json_encode($pickIds), $multiResults->count(), $ms);
foreach ($multiResults as $r) {
    printf("    id=%d sim=%.4f \"%s\"\n", $r->record->id, $r->similarity, $r->record->title);
}
check('returns at most '.count($pickIds).' results',
    $multiResults->count() <= count($pickIds));
check('every result is in the scoped id set',
    $multiResults->every(fn ($r) => in_array($r->record->id, $pickIds, true)));

subsection('7c. Chunked: search ids restricts chunks to specific projects');

// Create a second project so we have two to scope between
$betaProject = Project::create(['title' => 'Unrelated Brief', 'body' => "# Beta\n\nA totally unrelated project about gardening tools and seasonal planting schedules."]);
app(ChunkContentService::class)->reconcile($betaProject);

[$alphaScoped, $ms] = timed(fn () => Atlas::similaritySearch(Project::class, 'who runs the ingestion service', [
    'limit' => 5,
    'ids' => $alphaProject->id,
]));
printf("  ids => %d (alpha only) — got %d chunk(s) in %.0f ms\n", $alphaProject->id, $alphaScoped->count(), $ms);
foreach ($alphaScoped as $r) {
    printf("    project=%d ord=%-2d sim=%.4f heading: %s\n",
        $r->record->id, $r->ord, $r->similarity, $r->headingPath ?? '—');
}
check('every result belongs to alpha project',
    $alphaScoped->every(fn ($r) => $r->record->id === $alphaProject->id));

[$betaScoped, $ms] = timed(fn () => Atlas::similaritySearch(Project::class, 'who runs the ingestion service', [
    'limit' => 5,
    'ids' => $betaProject->id,
]));
printf("  ids => %d (beta only) — got %d chunk(s) in %.0f ms\n", $betaProject->id, $betaScoped->count(), $ms);
foreach ($betaScoped as $r) {
    printf("    project=%d ord=%-2d sim=%.4f heading: %s\n",
        $r->record->id, $r->ord, $r->similarity, $r->headingPath ?? '—');
}
check('beta-scoped results all belong to beta project',
    $betaScoped->every(fn ($r) => $r->record->id === $betaProject->id));
check('beta has chunks but they\'re semantically far from the query',
    $betaScoped->isEmpty() || $betaScoped->first()->similarity < $alphaScoped->first()->similarity,
    'beta top sim should be lower than alpha top sim');

subsection('7d. Empty ids = [] short-circuits without API call');

[$noResults, $ms] = timed(fn () => Atlas::similaritySearch(Project::class, 'anything', ['ids' => []]));
printf("  ids => [] — got %d result(s) in %.0f ms\n", $noResults->count(), $ms);
check('empty ids returns zero results', $noResults->count() === 0);
check('latency near zero (no embedding API call)', $ms < 50, sprintf('took %.0f ms', $ms));

subsection('7e. Agent tool with ids wired at construction');

$scopedTool = SimilaritySearch::usingModel(Project::class, limit: 3, ids: [$alphaProject->id]);
[$scopedToolResults, $ms] = timed(fn () => $scopedTool->handle(['query' => 'how does sharding work'], []));
printf("  Scoped tool returned %d result(s) in %.0f ms\n", $scopedToolResults->count(), $ms);
foreach ($scopedToolResults as $r) {
    printf("    project=%d sim=%.4f heading: %s\n", $r->record->id, $r->similarity, $r->headingPath ?? '—');
}
check('all scoped-tool results from alpha project',
    $scopedToolResults->every(fn ($r) => $r->record->id === $alphaProject->id));

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 8 — Latency baseline summary
// ═══════════════════════════════════════════════════════════════════════════

section('8. Latency baseline — RAG-feel summary');

$benchmarks = [
    'whole-record search (Note)' => fn () => Atlas::similaritySearch(Note::class, 'how do escalations work', ['limit' => 3]),
    'chunked search (Project)' => fn () => Atlas::similaritySearch(Project::class, 'how does idempotency work', ['limit' => 3]),
    'whole-record + ids scope (1 note)' => fn () => Atlas::similaritySearch(Note::class, 'q', ['ids' => $pickNoteId, 'limit' => 3]),
    'chunked + ids scope (1 project)' => fn () => Atlas::similaritySearch(Project::class, 'q', ['ids' => $alphaProject->id, 'limit' => 3]),
    'chunked + ids = []' => fn () => Atlas::similaritySearch(Project::class, 'q', ['ids' => []]),
];

printf("\n  %-45s %s\n", 'Operation', 'Wall-clock');
printf("  %s\n", str_repeat('─', 65));
foreach ($benchmarks as $label => $fn) {
    [$_, $ms] = timed($fn);
    printf("  %-45s %6.0f ms\n", $label, $ms);
}

// ═══════════════════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════════════════

section('Summary');

$totalNotes = Note::count();
$totalProjects = Project::count();
$totalChunks = Chunk::count();

printf("  Records: %d notes, %d projects, %d chunks\n", $totalNotes, $totalProjects, $totalChunks);
printf("  Embedding model: text-embedding-3-small (1536 dims)\n");
printf("  Cache: disabled (every query hit the real OpenAI API)\n");

if (! empty($failures)) {
    echo "\n  ".count($failures)." assertion(s) FAILED:\n";
    foreach ($failures as $f) {
        echo "    - {$f}\n";
    }
    exit(1);
}

echo "\n  All assertions passed. Embeddings + similarity search behave as expected.\n";
