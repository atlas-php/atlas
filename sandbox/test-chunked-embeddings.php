<?php

declare(strict_types=1);

/**
 * Chunked Embeddings Integration Test
 *
 * End-to-end exercise of the chunked-embeddings subsystem against the local
 * PostgreSQL database and the real OpenAI embedding API. Asserts and prints
 * every interesting state along the way so correctness is verifiable by
 * eye, not just by green tests.
 *
 * Sections:
 *   1. Save markdown with H2 + H3 + H4 structure → confirm content_hash is set.
 *   2. Run the reconciler → print every chunk (ord, heading_path, content)
 *      and assert heading-path attribution matches the source content.
 *   3. Edit one sentence and re-run → assert only the affected chunks
 *      re-embed; the rest are kept verbatim.
 *   4. Run several similarity queries → for each, print scored results
 *      with hydrated parent model. Assert that the top result's content
 *      actually contains the answer to the query (semantic correctness).
 *
 * Usage:
 *
 *   cd sandbox
 *   php artisan migrate:fresh           # one-time / when schema changes
 *   php test-chunked-embeddings.php
 *
 * Requires OPENAI_API_KEY in sandbox/.env and PostgreSQL with pgvector.
 */
$app = require __DIR__.'/bootstrap.php';

use App\Models\Project;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Persistence\Services\ChunkContentService;
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
// Shrink the chunk budget for the demo so the longer Notes on architecture
// section (6 paragraphs) actually splits into multiple chunks — that's what
// makes per-fact retrieval visible. The default 512 would pack all six into
// a single chunk and the specific facts (sharding, idempotency, circuit
// breaker) would be diluted in retrieval scoring.
$app['config']->set('atlas.embeddings.chunk_size', 150);
$app['config']->set('atlas.embeddings.chunk_overlap', 30);
AtlasConfig::refresh();

// ─── Preflight ──────────────────────────────────────────────────────────────

if (DB::connection()->getDriverName() !== 'pgsql') {
    echo "This demo requires PostgreSQL (DB_CONNECTION=pgsql). Aborting.\n";
    exit(1);
}

if (! Schema::hasTable('projects') || ! Schema::hasTable('atlas_chunks')) {
    echo "Missing tables — run `php artisan migrate:fresh` first.\n";
    exit(1);
}

DB::table('atlas_chunks')->delete();
DB::table('projects')->delete();

// ─── Output + assertion helpers ─────────────────────────────────────────────

$failures = [];

function section(string $title): void
{
    echo "\n".str_repeat('─', 76)."\n  {$title}\n".str_repeat('─', 76)."\n";
}

function preview(?string $text, int $width = 110): string
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
    $line = "  {$mark} {$name}";
    if ($detail !== '') {
        $line .= " — {$detail}";
    }
    echo $line."\n";
    if (! $ok) {
        $failures[] = $name.($detail !== '' ? ": {$detail}" : '');
    }
}

// ─── 1. Save a multi-section markdown doc with H2 + H3 + H4 ─────────────────

section('1. Save a multi-section markdown document (uses H2, H3, and H4)');

$body = <<<'MD'
# Project Overview

This project handles invoice ingestion for the regional finance team. It replaces a brittle spreadsheet workflow with a typed pipeline backed by the new billing service.

## Stakeholders

Anna Marquez owns the product roadmap and approves scope changes. Brian leads engineering and is the technical decision-maker. Cara handles customer enablement and writes the public release notes.

### Engineering team

Brian leads a four-person team. Pri owns the ingestion service. Devon owns the projection layer. Maria is the SRE embedded for the duration of the project.

### Customer enablement team

Cara coordinates with two solution engineers, Sam and Lin, who handle the deeper integration work for our top five enterprise accounts.

## Timeline

We start in May with a two-week discovery phase. The first internal beta drops in August. Public GA targets December, with a holiday freeze blocking any changes between December 15 and January 5.

## Risks

Two risks dominate this delivery.

### Data quality risk

Roughly fifteen percent of incoming invoices fail validation today and we will inherit that floor. We will need a triage workflow before launch.

#### Mitigation

We will publish a "rejected invoice" report weekly during beta and route everything through the new validation rules before GA.

### Integration timing risk

The billing rewrite is fragile. If billing slips by more than four weeks our beta window collapses.

## Notes on architecture

The pipeline uses an event sourcing pattern internally. Each ingested invoice produces an immutable event, and a projection rebuilds the queryable state. This trades write complexity for replayability — we can rebuild any view from raw events if a bug corrupts state.

The ingestion service writes events to a dedicated stream per tenant. Streams are sharded by tenant id so a single noisy customer cannot starve others; horizontal scaling adds shards rather than nodes. The choice of per-tenant streams costs us cross-tenant analytics complexity but buys us straightforward backpressure handling.

The projection layer reads each stream sequentially and writes to a denormalised read store optimised for the UI's query patterns. Read stores are recreated from scratch any time the projection shape changes, which removes the schema-migration coordination problem that the legacy spreadsheet workflow suffered from. Projection lag in production is typically under two seconds.

For idempotency, we tag every inbound invoice with a deterministic hash of its content plus a stable source identifier. Duplicate submissions resolve to the same event id and are dropped silently at the ingestion boundary. This handles the most common upstream bug — replay storms after a network blip — without operator involvement.

Observability is event-store-first. We surface a dashboard of "events per minute by source", "projection lag per tenant", and "invoice rejection reasons" rather than chasing line-of-business metrics through a tangle of database queries. Every alert points back to an event id we can replay manually if we need to investigate.

Failure handling follows a circuit-breaker pattern. When the billing service's API health checks fail three consecutive times within sixty seconds, the breaker opens and ingestion buffers events to a quarantine queue. Operators are paged. Once health is restored, the quarantined events drain in their original order, preserving the at-least-once delivery guarantee end-to-end.
MD;

$project = Project::create([
    'title' => 'Invoice Ingestion Project Brief',
    'body' => $body,
]);

echo "  Created project id={$project->id} title=\"{$project->title}\"\n";
echo '  body length: '.strlen($body)." chars\n\n";

check('content_hash set on save', $project->content_hash !== null);
check('indexed_hash is null pre-chunking', $project->indexed_hash === null);
check('content_hash matches sha-like format (xxh128, 32 hex chars)',
    is_string($project->content_hash) && strlen($project->content_hash) === 32 && ctype_xdigit($project->content_hash),
    "value: {$project->content_hash}");
check('morph class is fully-qualified', $project->getMorphClass() === 'App\Models\Project',
    "got: {$project->getMorphClass()}");

// ─── 2. Reconcile + inspect chunks ──────────────────────────────────────────

section('2. Run the reconciler — show every chunk');

$start = microtime(true);
app(ChunkContentService::class)->reconcile($project);
$elapsed = (microtime(true) - $start) * 1000;
$project->refresh();

echo sprintf("\n  reconcile complete in %.1f ms\n\n", $elapsed);

check('indexed_hash now matches content_hash', $project->indexed_hash === $project->content_hash);
check('indexed_at is set', $project->indexed_at !== null);
check('index_failure_count is zero', (int) $project->index_failure_count === 0);

$rows = Chunk::query()->where('chunkable_id', $project->id)->orderBy('ord')->get();

echo "\n  Chunks generated: {$rows->count()}\n\n";

foreach ($rows as $row) {
    printf("  ord=%-2d tokens=%-4d hash=%s\n", $row->ord, $row->token_count, substr($row->content_hash, 0, 8));
    printf("       heading_path: %s\n", $row->heading_path ?? '—');
    printf("       chunkable:    {$row->chunkable_type}:{$row->chunkable_id}\n");
    printf("       content:      %s\n\n", preview($row->content));
}

// Assertions on each chunk's structure
check('every chunk has chunkable_type=App\\Models\\Project',
    $rows->every(fn ($r) => $r->chunkable_type === 'App\Models\Project'));
check('every chunk has chunkable_id matching the project',
    $rows->every(fn ($r) => (int) $r->chunkable_id === $project->id));
check('every chunk has a non-empty embedding (pgvector)',
    $rows->every(fn ($r) => $r->embedding !== null));
check('every chunk has the configured embedding_model recorded',
    $rows->every(fn ($r) => $r->embedding_model === 'text-embedding-3-small'));
check('chunk ord values are 0..N-1 in order',
    $rows->pluck('ord')->values()->all() === range(0, $rows->count() - 1));

// Specifically verify heading_path attribution against source content.
$headingsSeen = $rows->pluck('heading_path')->filter()->values()->all();
$expectedHeadingFragments = [
    'Project Overview',
    'Project Overview > Stakeholders',
    'Project Overview > Stakeholders > Engineering team',
    'Project Overview > Stakeholders > Customer enablement team',
    'Project Overview > Timeline',
    'Project Overview > Risks',
    'Project Overview > Risks > Data quality risk',
    'Project Overview > Risks > Integration timing risk',
    'Project Overview > Notes on architecture',
];

foreach ($expectedHeadingFragments as $expected) {
    check("heading_path includes \"{$expected}\"", in_array($expected, $headingsSeen, true));
}

// Spot-check that section content matches its heading (no cross-section bleed).
$stakeholdersChunk = $rows->firstWhere('heading_path', 'Project Overview > Stakeholders');
check('Stakeholders chunk content mentions Anna/Brian/Cara',
    $stakeholdersChunk !== null && str_contains($stakeholdersChunk->content, 'Anna Marquez'));

$engineeringChunk = $rows->firstWhere('heading_path', 'Project Overview > Stakeholders > Engineering team');
check('Engineering team chunk mentions Pri/Devon/Maria',
    $engineeringChunk !== null && str_contains($engineeringChunk->content, 'Pri owns'));

$dataRiskChunk = $rows->firstWhere('heading_path', 'Project Overview > Risks > Data quality risk');
check('Data quality risk chunk mentions the 15% validation failure stat',
    $dataRiskChunk !== null && str_contains($dataRiskChunk->content, 'fifteen percent'));

$firstRunHashes = $rows->pluck('content_hash')->all();

// ─── 3. Edit one sentence and re-run ────────────────────────────────────────

section('3. Edit one sentence and re-run — assert only that chunk re-embeds');

$editedBody = str_replace(
    'Pri owns the ingestion service.',
    'Pri owns the ingestion service and the data validation rules.',
    $body,
);
$project->update(['body' => $editedBody]);
$project->refresh();

check('content_hash changed after edit', $project->content_hash !== $project->indexed_hash);

$start = microtime(true);
app(ChunkContentService::class)->reconcile($project);
$elapsed = (microtime(true) - $start) * 1000;

$afterRows = Chunk::query()->where('chunkable_id', $project->id)->orderBy('ord')->get();
$kept = 0;
$rechunked = 0;
foreach ($afterRows as $row) {
    if (in_array($row->content_hash, $firstRunHashes, true)) {
        $kept++;
    } else {
        $rechunked++;
    }
}

echo sprintf("\n  reconcile complete in %.1f ms\n", $elapsed);
echo "  chunks kept (unchanged hash): {$kept}\n";
echo "  chunks newly embedded: {$rechunked}\n";
echo '  total chunks now: '.$afterRows->count()."\n\n";

check('only one chunk was re-embedded (the one we edited)', $rechunked === 1, "got {$rechunked}");
check('kept count is total minus rechunked', $kept === $afterRows->count() - $rechunked);
check('project indexed_hash matches new content_hash after re-run', $project->fresh()->indexed_hash === $project->fresh()->content_hash);

// ─── 4. Similarity search with assertions on returned content ──────────────

section('4. Similarity search — check returned content is the right answer');

$queries = [
    ['question' => 'who owns the product roadmap', 'mustContain' => 'Anna Marquez'],
    ['question' => 'what is the public launch date', 'mustContain' => 'December'],
    ['question' => 'who runs the ingestion service', 'mustContain' => 'Pri'],
    ['question' => 'why are we worried about data quality', 'mustContain' => 'fifteen percent'],
    ['question' => 'how does the pipeline architecture handle replays', 'mustContain' => 'event sourcing'],
    ['question' => 'what happens during the holiday freeze', 'mustContain' => 'December 15'],
    // The next four queries probe the multi-paragraph Notes on architecture
    // section — they verify the chunker packs paragraphs correctly within a
    // section and that within-section overlap preserves retrieval context.
    ['question' => 'how does the system shard tenant traffic', 'mustContain' => 'shard'],
    ['question' => 'how is idempotency enforced on incoming invoices', 'mustContain' => 'deterministic hash'],
    ['question' => 'what does observability look like', 'mustContain' => 'event-store'],
    ['question' => 'how do quarantined events get processed', 'mustContain' => 'quarantine'],
];

foreach ($queries as $q) {
    echo "\n  Query: \"{$q['question']}\"\n";

    $results = Atlas::similaritySearch(Project::class, $q['question'], ['limit' => 3]);

    if ($results->isEmpty()) {
        check('  → returns results', false);

        continue;
    }

    foreach ($results as $i => $result) {
        printf("    %d. similarity=%.4f  heading: %s\n",
            $i + 1, $result->similarity, $result->headingPath ?? '—');
        printf("       owner: \"%s\" (id=%d, type=%s)\n",
            $result->record->title, $result->record->id, $result->record::class);
        printf("       %s\n", preview($result->content, 110));
    }

    $top = $results->first();
    check('  → top result content mentions "'.$q['mustContain'].'"',
        str_contains($top->content, $q['mustContain']),
        'top content: '.preview($top->content, 80));
    check('  → top result has hydrated parent (Project model with title)',
        $top->record instanceof Project && $top->record->title === $project->title);
}

// ─── 5. Final summary ───────────────────────────────────────────────────────

section('Summary');

$totalChunks = Chunk::query()->where('chunkable_id', $project->id)->count();
$embeddingModel = Chunk::query()->where('chunkable_id', $project->id)->value('embedding_model');

echo "  Project id={$project->id} \"{$project->title}\"\n";
echo '  chunkable_type stored: '.$afterRows->first()->chunkable_type."\n";
echo "  Chunks stored: {$totalChunks}\n";
echo "  Embedding model: {$embeddingModel}\n";
echo "  Re-embed efficiency on edit: {$rechunked}/".count($firstRunHashes)." chunks rebuilt\n";

if (! empty($failures)) {
    echo "\n  ".count($failures)." assertion(s) FAILED:\n";
    foreach ($failures as $f) {
        echo "    - {$f}\n";
    }
    exit(1);
}

echo "\n  All assertions passed.\n";
