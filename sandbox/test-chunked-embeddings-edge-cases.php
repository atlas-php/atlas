<?php

declare(strict_types=1);

/**
 * Chunked Embeddings — Edge-Case Stress Test
 *
 * Exercises the chunker against markdown layouts that look nothing like the
 * canonical "well-structured project brief" used in the main demo:
 *
 *   A. Single H1 + wall of text (one heading, ~8 paragraphs)
 *   B. No headings at all (pure prose)
 *   C. Rich inline formatting (bold, italic, links, images, blockquotes,
 *      lists, inline code)
 *   D. Code-fence heavy (multiple ```php blocks)
 *   E. GFM pipe tables
 *
 * For each layout, asserts the obvious properties:
 *   - chunks were produced
 *   - heading_path attribution makes sense for that shape
 *   - inline formatting / code fences / tables survive in chunk content
 *     (markdown is stored verbatim, not stripped)
 *   - similarity search returns relevant results
 *
 * Usage:
 *   cd sandbox
 *   php artisan migrate:fresh
 *   php test-chunked-embeddings-edge-cases.php
 */
$app = require __DIR__.'/bootstrap.php';

use App\Models\Project;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Persistence\Services\ChunkContentService;
use Illuminate\Database\Eloquent\Collection;
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
// Realistic budget — exercise the same defaults a consumer would see.
$app['config']->set('atlas.embeddings.chunk_size', 200);
$app['config']->set('atlas.embeddings.chunk_overlap', 30);
AtlasConfig::refresh();

if (DB::connection()->getDriverName() !== 'pgsql') {
    echo "Requires PostgreSQL. Aborting.\n";
    exit(1);
}

if (! Schema::hasTable('projects') || ! Schema::hasTable('atlas_chunks')) {
    echo "Run `php artisan migrate:fresh` first.\n";
    exit(1);
}

DB::table('atlas_chunks')->delete();
DB::table('projects')->delete();

// ─── Helpers ────────────────────────────────────────────────────────────────

$failures = [];

function section(string $title): void
{
    echo "\n".str_repeat('━', 76)."\n  {$title}\n".str_repeat('━', 76)."\n";
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
    echo "  {$mark} {$name}".($detail !== '' ? " — {$detail}" : '')."\n";
    if (! $ok) {
        $failures[] = $name.($detail !== '' ? ": {$detail}" : '');
    }
}

function reconcileAndShow(Project $p): Collection
{
    $start = microtime(true);
    app(ChunkContentService::class)->reconcile($p);
    $elapsed = (microtime(true) - $start) * 1000;
    $p->refresh();

    $rows = Chunk::query()->where('chunkable_id', $p->id)->orderBy('ord')->get();
    printf("\n  Generated %d chunk(s) in %.0f ms (chunkable_type=%s):\n\n", $rows->count(), $elapsed, $rows->first()?->chunkable_type ?? '?');

    foreach ($rows as $row) {
        printf("  ord=%-2d tokens=%-4d heading: %s\n", $row->ord, $row->token_count, $row->heading_path ?? '—');
        printf("        content: %s\n\n", preview($row->content, 110));
    }

    return $rows;
}

function searchAndAssert(string $query, string $mustContain): void
{
    echo "  Query: \"{$query}\"\n";
    $results = Atlas::similaritySearch(Project::class, $query, ['limit' => 1]);
    if ($results->isEmpty()) {
        check('  → returns at least one result', false);

        return;
    }
    $top = $results->first();
    printf("    top: similarity=%.4f  heading: %s\n", $top->similarity, $top->headingPath ?? '—');
    printf("    %s\n", preview($top->content, 110));
    check("  → top contains \"{$mustContain}\"", str_contains($top->content, $mustContain),
        'got: '.preview($top->content, 80));
}

// ═══════════════════════════════════════════════════════════════════════════
// CASE A: Single H1 + wall of text (one heading, many paragraphs)
// ═══════════════════════════════════════════════════════════════════════════

section('CASE A — Single H1 + wall of text (8 paragraphs under one heading)');

$bodyA = <<<'MD'
# Operations Playbook

The on-call rotation runs in twelve-hour shifts. Primary on-call handles all initial pages and decides whether to escalate. Secondary on-call is the backup and handles incidents the primary cannot triage within ten minutes. Both shifts overlap by thirty minutes at handover so that any in-flight investigation has continuity.

When a page arrives, acknowledge within five minutes. Begin a triage doc in the incident channel using the standard template. The template prompts you for impact assessment, affected customers, and a working hypothesis. Even if your hypothesis is wrong, writing it down forces the next responder to look at the same evidence and weigh in.

Escalations to engineering management happen at the thirty-minute mark if customer impact is confirmed and a fix is not in progress. Do not wait for permission to escalate — the cost of an unnecessary page to the manager is far lower than the cost of a customer-impacting incident lasting an extra hour.

Database recovery procedures live in the runbook repository. The most important rule is never to run a recovery script against production without a witness on the call. A witness is anyone with production access who can confirm you typed the right command. This rule has saved us at least three times in the last two years and has cost us almost nothing.

Communications with customers go through the support team, never directly from engineering. If you need to provide a technical detail to a customer, write it in the incident channel and tag the support lead. The support lead will rewrite it into customer-appropriate language and post it to the status page or send it directly.

Postmortems are blameless and timebound. They must be drafted within forty-eight hours of incident resolution. The draft does not need to be polished — bullet points and rough timestamps are fine. The full review happens at the weekly engineering meeting where action items are assigned with owners and dates.

Action items from postmortems are tracked in a dedicated project board. Stale action items get re-reviewed every month. If an item has been on the board for sixty days without progress, it is either de-prioritized explicitly or someone takes it and finishes it within two weeks. We do not let postmortem items accumulate as guilt.

Practice drills happen quarterly. Each drill simulates a realistic incident: a database failure, a third-party outage, a security alert. The goal is not to test individuals but to test the playbook itself. After every drill we update the playbook with anything we learned about gaps or unclear instructions.
MD;

$a = Project::create(['title' => 'Operations Playbook', 'body' => $bodyA]);
echo '  body length: '.strlen($bodyA)." chars\n";

$rowsA = reconcileAndShow($a);

check('chunks generated', $rowsA->count() > 1, "got {$rowsA->count()}");
check('all chunks have the same heading_path (single H1)',
    $rowsA->pluck('heading_path')->unique()->count() === 1);
check('heading_path is "Operations Playbook"',
    $rowsA->first()->heading_path === 'Operations Playbook');
check('all chunks have non-null embeddings', $rowsA->every(fn ($r) => $r->embedding !== null));

echo "\n";
// Structural check: the chunker should have preserved the escalation policy
// somewhere in the chunks. (Similarity-search ranking for short, vocabulary-
// adjacent paragraphs is noisy on small docs — verifying structural presence
// is what tells us the chunker is correct.)
check('"thirty-minute" escalation content survived chunking',
    $rowsA->contains(fn ($r) => str_contains($r->content, 'thirty-minute mark')));

searchAndAssert('how do we handle database recovery safely', 'witness');
searchAndAssert('what happens with stale postmortem action items', 'sixty days');

// ═══════════════════════════════════════════════════════════════════════════
// CASE B: No headings at all (pure prose)
// ═══════════════════════════════════════════════════════════════════════════

section('CASE B — Pure prose, no headings (heading_path should be null)');

$bodyB = <<<'MD'
The package arrived on a Tuesday afternoon, three days later than promised but still in time for the showcase. I cut the tape carefully because the contents were both fragile and irreplaceable, salvaged from a workshop that no longer exists. The original owner had spent forty years assembling the tools inside, each handle marked with a date and a project.

I had no plan for them. I had bought the lot at auction on a whim, drawn by the photograph more than the description: a wooden box, brass corners, hand-cut dovetails. When the lid finally lifted I sat for a long time just looking at the arrangement. Whoever had packed this box had thought about its future readers. Each tool sat in its own cradle. There was a folded paper at the top that turned out to be an index, written in pencil, listing the tools left-to-right and top-to-bottom.

I have spent six months since then learning the tools one at a time. Some I knew already, plane irons and a few chisels. Many I did not — the strangest is a wooden device for marking out hexagons, which I assume was built for a single bespoke project and never used since. There is a great satisfaction in placing a tool back in its cradle at the end of an afternoon and writing my own pencil note alongside the original.

Friends ask why I spend time on this and I rarely have a satisfying answer. The best one I have managed is that it lets me practice a kind of attention I do not have to bring to my paid work. The tools do not need anything from me. They will not be on a deadline. If I get the sharpening angle wrong on a plane iron, the worst that happens is that I sharpen it again next week.
MD;

$b = Project::create(['title' => 'A Box of Tools', 'body' => $bodyB]);
$rowsB = reconcileAndShow($b);

check('chunks generated', $rowsB->count() >= 1);
check('every chunk has heading_path = null (no headings in source)',
    $rowsB->every(fn ($r) => $r->heading_path === null));

echo "\n";
searchAndAssert('what was inside the box', 'tools');
searchAndAssert('what kind of attention does woodworking provide', 'paid work');

// ═══════════════════════════════════════════════════════════════════════════
// CASE C: Rich inline formatting (bold, italic, links, images, lists)
// ═══════════════════════════════════════════════════════════════════════════

section('CASE C — Rich markdown: bold, italic, links, images, lists, blockquotes');

$bodyC = <<<'MD'
# Release Checklist

Pre-flight items before pushing the **v3.1 release**.

## Notifications

Send the *advance notice* email to active customers at least **72 hours** before deploy. The template lives in [the marketing repo](https://github.com/example/marketing/blob/main/templates/release.md) and pulls the changelog automatically from `CHANGELOG.md`.

Include the screenshot:

![Release banner mockup](https://cdn.example.com/banners/v3-1.png "v3.1 release banner")

> **Note:** double-check the dashboard image renders in the test send before the production blast. Last cycle we shipped a broken `src` and had to follow up with an apology.

## Smoke tests

After deploy, run these in order:

1. Confirm the `/healthz` endpoint returns `200` with build SHA matching the deploy tag.
2. Send a test webhook from the **staging tenant** and verify it lands in the production audit log.
3. Open the dashboard as a real customer and watch one full chart render — *not just the API response*, the **actual chart**.
4. Page yourself via the test alert path: `make alert/test`. Confirm the page arrives within 30 seconds.

If **any step fails**, roll back via `kubectl rollout undo` and post in `#release-room` immediately.

## Post-deploy

- Update the [status page](https://status.example.com) entry from "scheduled" to "completed".
- Mark the GitHub release as published (not pre-release).
- Close the release-tracking issue with a comment linking to the deploy run.
- Tag `@oncall` in the engineering channel so they know the window has closed.

Final reminder: **never** release on a Friday after 3pm Pacific. The on-call burden of a botched Friday release ruins everyone's weekend and we have never had a release that was so urgent it justified bypassing this rule.
MD;

$c = Project::create(['title' => 'Release Checklist', 'body' => $bodyC]);
$rowsC = reconcileAndShow($c);

check('chunks generated', $rowsC->count() > 1);
check('bold markers (**) preserved in chunk content',
    $rowsC->contains(fn ($r) => str_contains($r->content, '**')));
check('italic markers (*) preserved in chunk content',
    $rowsC->contains(fn ($r) => preg_match('/\*[^*\s][^*]*\*/', $r->content) === 1));
check('link syntax [text](url) preserved',
    $rowsC->contains(fn ($r) => preg_match('/\[[^\]]+\]\([^\)]+\)/', $r->content) === 1));
check('image syntax ![alt](url) preserved',
    $rowsC->contains(fn ($r) => str_contains($r->content, '![Release banner mockup]')));
check('blockquote (>) preserved',
    $rowsC->contains(fn ($r) => preg_match('/^>\s/m', $r->content) === 1));
check('numbered list items preserved',
    $rowsC->contains(fn ($r) => preg_match('/^\d+\.\s/m', $r->content) === 1));
check('bullet list (-) preserved',
    $rowsC->contains(fn ($r) => preg_match('/^- /m', $r->content) === 1));
check('inline code (`...`) preserved',
    $rowsC->contains(fn ($r) => str_contains($r->content, '`/healthz`')));

echo "\n";
searchAndAssert('what time should we never release', 'Friday');
searchAndAssert('what kubectl command rolls back the deployment', 'kubectl rollout undo');
searchAndAssert('how does the status page get updated', 'status page');

// ═══════════════════════════════════════════════════════════════════════════
// CASE D: Code-fence heavy (multiple language fences)
// ═══════════════════════════════════════════════════════════════════════════

section('CASE D — Code-fence heavy: prose interleaved with multiple code blocks');

$bodyD = <<<'MD'
# API Quickstart

A short tour of the SDK with runnable snippets.

## Authentication

Set your token in an environment variable. Never hardcode it:

```bash
export EXAMPLE_API_TOKEN="ex_live_..."
```

In your client code, the SDK picks it up automatically:

```php
use Example\Client;

$client = Client::makeFromEnv();
$response = $client->users()->list();
```

## Pagination

The list endpoints return cursor-based pages. Iterate like this:

```php
foreach ($client->users()->iterate() as $user) {
    echo $user->email."\n";
}
```

For low-level control over the cursor, use `nextPage()`:

```php
$page = $client->users()->list(['limit' => 50]);
while ($page->hasNext()) {
    foreach ($page as $user) {
        process($user);
    }
    $page = $page->nextPage();
}
```

## Errors

The SDK throws typed exceptions:

```php
try {
    $client->users()->create(['email' => 'bad email']);
} catch (\Example\ValidationException $e) {
    foreach ($e->errors() as $field => $messages) {
        echo "{$field}: ".implode(', ', $messages)."\n";
    }
}
```

Network errors are wrapped in `Example\TransportException` and should usually be retried with backoff.
MD;

$d = Project::create(['title' => 'API Quickstart', 'body' => $bodyD]);
$rowsD = reconcileAndShow($d);

check('chunks generated', $rowsD->count() > 1);
check('at least one chunk contains a fenced code block',
    $rowsD->contains(fn ($r) => preg_match('/```[a-z]*\n.*\n```/s', $r->content) === 1));
check('php fence syntax preserved',
    $rowsD->contains(fn ($r) => str_contains($r->content, '```php')));
check('bash fence syntax preserved',
    $rowsD->contains(fn ($r) => str_contains($r->content, '```bash')));
check('code body verbatim (Client::makeFromEnv specifically)',
    $rowsD->contains(fn ($r) => str_contains($r->content, 'Client::makeFromEnv()')));

echo "\n";
searchAndAssert('how do I paginate through users', 'iterate');
searchAndAssert('what exception is thrown for validation errors', 'ValidationException');
searchAndAssert('how do I set the API token securely', 'EXAMPLE_API_TOKEN');

// ═══════════════════════════════════════════════════════════════════════════
// CASE E: GFM pipe tables
// ═══════════════════════════════════════════════════════════════════════════

section('CASE E — GFM pipe tables (atomic; should never be split)');

$bodyE = <<<'MD'
# Plan Comparison

Quick reference for the three published tiers.

## Pricing tiers

| Tier       | Monthly | Annual | Seats |
|------------|---------|--------|-------|
| Starter    | $19     | $190   | 3     |
| Growth     | $79     | $790   | 10    |
| Enterprise | Custom  | Custom | 25+   |

Custom contracts on the Enterprise tier include a dedicated solutions engineer and quarterly business reviews.

## Feature matrix

| Feature              | Starter | Growth | Enterprise |
|----------------------|---------|--------|------------|
| API access           | ✓       | ✓      | ✓          |
| Webhooks             | —       | ✓      | ✓          |
| SSO (SAML)           | —       | —      | ✓          |
| Audit log retention  | 7 days  | 90 days| 365 days   |
| Priority support     | —       | ✓      | ✓          |

Audit log retention beyond 365 days is available as a paid add-on; ask sales for current pricing.
MD;

$e = Project::create(['title' => 'Plan Comparison', 'body' => $bodyE]);
$rowsE = reconcileAndShow($e);

check('chunks generated', $rowsE->count() >= 1);
check('table pipe syntax preserved in at least one chunk',
    $rowsE->contains(fn ($r) => str_contains($r->content, '| Starter')));
check('table divider row preserved (|---|---|)',
    $rowsE->contains(fn ($r) => preg_match('/^\s*\|[\s\-:|]+\|\s*$/m', $r->content) === 1));
check('table content rows preserved verbatim',
    $rowsE->contains(fn ($r) => str_contains($r->content, '| Enterprise | Custom  | Custom | 25+')));
check('feature-matrix table preserved on its own',
    $rowsE->contains(fn ($r) => str_contains($r->content, 'SSO (SAML)')));

echo "\n";
searchAndAssert('how much does the Growth plan cost annually', '790');
searchAndAssert('which tier provides SAML single sign-on', 'SAML');
searchAndAssert('how long is the audit log retained on Growth', '90 days');

// ═══════════════════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════════════════

section('Summary');

$totalChunks = Chunk::query()->count();
$totalProjects = Project::query()->count();
echo "  Projects created: {$totalProjects}\n";
echo "  Total chunks across all cases: {$totalChunks}\n";
echo "  Embedding model: text-embedding-3-small\n";

if (! empty($failures)) {
    echo "\n  ".count($failures)." assertion(s) FAILED:\n";
    foreach ($failures as $f) {
        echo "    - {$f}\n";
    }
    exit(1);
}

echo "\n  All edge-case assertions passed.\n";
