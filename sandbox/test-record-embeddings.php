<?php

declare(strict_types=1);

/**
 * Record Embeddings (HasVectorEmbeddings) Integration Test
 *
 * Sibling to test-chunked-embeddings.php — exercises the OTHER mode atlas
 * supports: one vector per record stored on the model's own table. Useful
 * for short, atomic items (chat messages, prompts, named entities) where
 * you don't want chunking.
 *
 * Walks the consumer flow:
 *   1. Save several Notes — trait's saving hook auto-generates an embedding
 *      via the real OpenAI API (one API call per record on save).
 *   2. Run Atlas::similaritySearch(Note::class, $query) — the SAME unified
 *      facade used for chunked models. Auto-dispatches to whole-record
 *      mode because Note implements VectorEmbeddable.
 *   3. Print each query's top results with similarity score and the
 *      embedded text, then assert top-1 hits the expected record.
 *
 * Usage:
 *   cd sandbox
 *   php artisan migrate:fresh
 *   php test-record-embeddings.php
 *
 * Requires OPENAI_API_KEY in sandbox/.env and PostgreSQL with pgvector.
 */
$app = require __DIR__.'/bootstrap.php';

use App\Models\Note;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\SearchResult;
use Atlasphp\Atlas\Tools\SimilaritySearch;
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
$app['config']->set('atlas.persistence.enabled', true); // HasVectorEmbeddings::shouldGenerateEmbedding gates on this
AtlasConfig::refresh();

if (DB::connection()->getDriverName() !== 'pgsql') {
    echo "Requires PostgreSQL (DB_CONNECTION=pgsql). Aborting.\n";
    exit(1);
}

if (! Schema::hasTable('notes')) {
    echo "Missing notes table — run `php artisan migrate:fresh` first.\n";
    exit(1);
}

DB::table('notes')->delete();

// ─── Helpers ────────────────────────────────────────────────────────────────

$failures = [];

function section(string $title): void
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

// ─── 1. Save records — trait auto-embeds via the real API ───────────────────

section('1. Create notes — HasVectorEmbeddings auto-generates the embedding on save');

$seed = [
    ['title' => 'On-call escalation policy',
        'body' => 'Pages escalate to the engineering manager at the thirty-minute mark when customer impact is confirmed and a fix is not in progress.'],
    ['title' => 'Database recovery rule',
        'body' => 'Never run a recovery script in production without a witness on the call. The witness confirms the command before it executes.'],
    ['title' => 'Release timing',
        'body' => 'Public GA targets December. The holiday freeze blocks any changes between December 15 and January 5.'],
    ['title' => 'Stakeholders directory',
        'body' => 'Anna Marquez owns the product roadmap. Brian leads engineering. Cara handles customer enablement.'],
    ['title' => 'Pagination usage',
        'body' => 'Iterate cursor-based pages with $client->users()->iterate() — emits each record without holding the whole set in memory.'],
    ['title' => 'Idempotency strategy',
        'body' => 'Tag each invoice with a deterministic hash of its content plus the source identifier; duplicate submissions resolve to the same event id and are dropped.'],
];

foreach ($seed as $row) {
    $note = Note::create($row);
    echo "  Saved note id={$note->id} \"{$note->title}\"";
    echo $note->embedding !== null ? '  (embedding stored)' : '  (NO EMBEDDING!)';
    echo "\n";
}

$count = Note::query()->count();
check('all notes were created', $count === count($seed), "got {$count}");
check('every note has a populated embedding column', Note::query()->whereNotNull('embedding')->count() === count($seed));
check('every note has an embedding_at timestamp', Note::query()->whereNotNull('embedding_at')->count() === count($seed));

// ─── 2. Run similarity searches through the unified facade ──────────────────

section('2. Atlas::similaritySearch(Note::class, $query) — same facade as chunked');

$queries = [
    ['question' => 'when do we escalate to engineering management',  'expectedTitle' => 'On-call escalation policy'],
    ['question' => 'what is the rule for running database recovery', 'expectedTitle' => 'Database recovery rule'],
    ['question' => 'when does the holiday freeze end',                'expectedTitle' => 'Release timing'],
    ['question' => 'who owns the product roadmap',                    'expectedTitle' => 'Stakeholders directory'],
    ['question' => 'how do I iterate paginated records',              'expectedTitle' => 'Pagination usage'],
    ['question' => 'how do we deduplicate invoice submissions',       'expectedTitle' => 'Idempotency strategy'],
];

foreach ($queries as $q) {
    echo "\n  Query: \"{$q['question']}\"\n";

    $results = Atlas::similaritySearch(Note::class, $q['question'], ['limit' => 3]);

    if ($results->isEmpty()) {
        check('  → returns at least one result', false);

        continue;
    }

    foreach ($results as $i => $result) {
        printf("    %d. similarity=%.4f  record: \"%s\" (id=%d)\n",
            $i + 1, $result->similarity, $result->record->title, $result->record->id);
        printf("       content: %s\n", preview($result->content));
        printf("       headingPath: %s   ord: %s\n",
            $result->headingPath ?? '— (record-mode)',
            $result->ord === null ? '— (record-mode)' : (string) $result->ord);
    }

    $top = $results->first();
    check("  → top result matches expected note \"{$q['expectedTitle']}\"",
        $top->record->title === $q['expectedTitle'],
        "got: \"{$top->record->title}\"");
    check('  → top result is a Note instance', $top->record instanceof Note);
}

// ─── 3. Confirm the dispatch chose record-mode (not chunk-mode) ─────────────

section('3. Confirm Atlas::similaritySearch dispatched to whole-record mode');

$sample = Atlas::similaritySearch(Note::class, 'anything', ['limit' => 1])->first();
check('record-mode results have null headingPath', $sample->headingPath === null);
check('record-mode results have null ord', $sample->ord === null);
check('record-mode content equals the model\'s getEmbeddableContent()',
    $sample->content === $sample->record->getEmbeddableContent());

// ─── 4. Edit a record → embedding is regenerated automatically ──────────────

section('4. Edit a record — saving hook regenerates the embedding');

$note = Note::query()->where('title', 'Release timing')->first();
$beforeVector = $note->embedding;
$beforeAt = $note->embedding_at;

$note->update(['body' => 'Public GA now targets March. The previous December date was pushed back.']);
$note->refresh();

check('embedding regenerated on save', $note->embedding !== $beforeVector);
check('embedding_at timestamp advanced', $note->embedding_at > $beforeAt);

// Re-querying for the new content should now hit the updated record.
$results = Atlas::similaritySearch(Note::class, 'when does GA ship in March', ['limit' => 1]);
$top = $results->first();
echo "\n  After edit, top hit for \"when does GA ship in March\":\n";
printf("    similarity=%.4f  record: \"%s\"\n", $top->similarity, $top->record->title);
printf("    content: %s\n", preview($top->content));
check('top hit is the edited record', $top->record->id === $note->id);

// ─── 5. Verify the agent tool can also drive record-mode search ─────────────

section('5. SimilaritySearch agent tool routes to record-mode for Note');

$tool = SimilaritySearch::usingModel(Note::class, limit: 2);
$toolResults = $tool->handle(['query' => 'database recovery witness rule'], []);

check('agent tool returned results', $toolResults->count() > 0);
check('agent tool result is a SearchResult',
    $toolResults->first() instanceof SearchResult);
echo "  top: \"{$toolResults->first()->record->title}\"\n";

// ─── Summary ────────────────────────────────────────────────────────────────

section('Summary');

echo "  Notes stored: {$count}\n";
echo "  Search facade: Atlas::similaritySearch(Note::class, …)\n";
echo "  Dispatch path: RecordSearchService (via VectorEmbeddable interface)\n";

if (! empty($failures)) {
    echo "\n  ".count($failures)." assertion(s) FAILED:\n";
    foreach ($failures as $f) {
        echo "    - {$f}\n";
    }
    exit(1);
}

echo "\n  All assertions passed.\n";
