# Batch

Submit large sets of independent requests as one deferred job and pay **~50% less**. The provider processes the batch within its completion window (up to 24h, often much faster) and Atlas brings the results back for you.

Batch is ideal for latency-tolerant fan-out work — captioning thousands of images, classifying a backlog, embedding a corpus, nightly summarization — anything where you don't need the answer *this second* and the requests don't depend on each other.

## Provider & modality support

| Provider | Batchable modalities |
|----------|----------------------|
| **OpenAI** | `text` (including vision input), `embeddings` |
| **Anthropic** | `text` (including vision input) |
| **Google** (Gemini) | `text` (including vision input) |

::: tip
Asking a provider to batch a modality it doesn't support — or a non-batchable modality (audio, voice, rerank, …) — throws `UnsupportedFeatureException` up front. xAI batch, image/video batch, and embeddings batch for Anthropic/Google are a planned follow-up.
:::

## What can and cannot be batched

A batch line is a single, one-shot request resolved later and out of band. What survives into a batch is everything that's part of the request body:

- ✅ Messages, instructions, **vision input**
- ✅ **Structured output** (JSON schema)
- ✅ **Reasoning** effort
- ✅ Temperature, max tokens, provider options

What **cannot** be batched (and is rejected at build time, never silently dropped):

- ❌ **Tools** — the tool-execution loop needs synchronous round-trips. Use a synchronous request or an agent instead.
- ❌ **Agents** — an agent *is* the tool loop.
- ❌ **Per-request middleware** — batch bypasses the synchronous middleware pipeline.

## Stateless usage

**No persistence required.** Submit and get a batch id back immediately, then poll the provider yourself — no database, no migrations, no setup. Use this when you manage batch state in your own app and just want Atlas for the provider API. (Everything below works with `atlas.persistence.enabled` off.)

Batch works the same across providers — swap the string (`Atlas::batch('anthropic')`, `Atlas::batch('google')`); each maps to that provider's native batch endpoint.

```php
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Input\Image;

$batch = Atlas::batch('openai');

foreach ($images as $image) {
    $batch->add(
        Atlas::text()->model('gpt-5')->message('Caption this image.', [Image::fromUrl($image->url)]),
        key: (string) $image->id, // echoed back on the result — your trace-back key
    );
}

$response = $batch->submit();        // returns immediately
$response->batchId;                  // e.g. "batch_abc123"

// later…
$status  = Atlas::provider('openai')->batchStatus($response->batchId);
$results = Atlas::provider('openai')->batchResults($response->batchId);

foreach ($results as $result) {
    if ($result->succeeded()) {
        Image::find($result->customId)?->update(['caption' => $result->response->text]);
    }
}
```

## Tracked usage (recommended)

Persistence is **ambient**: with [persistence](/guides/persistence) enabled, `submit()` automatically persists a `BatchJob` and Atlas brings the results in for you — no extra call. Just schedule the poll command and listen for completion. (With persistence off, the same `submit()` is stateless and returns a `BatchResponse`, as above.)

```php
$job = Atlas::batch('openai')
    ->add(Atlas::embed()->fromInput($chunk->text), key: (string) $chunk->id)
    ->submit();                      // returns a BatchJob model (persistence on)
```

Schedule the poller (it advances every open job and hydrates completed ones):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('atlas:batch-poll')->everyFiveMinutes();
```

React to completion and write results back to your own models:

```php
use Atlasphp\Atlas\Events\BatchCompleted;

class WriteCaptionsBack
{
    public function handle(BatchCompleted $event): void
    {
        foreach ($event->job->results()->where('status', 'succeeded')->cursor() as $result) {
            Image::where('id', $result->custom_id)->update(['caption' => $result->response['text']]);
        }
    }
}
```

## Large jobs: chunking & groups

A single batch can hold tens of thousands of requests, but you'll often split a large workload into several batches (for file-size limits, progress granularity, or isolated retries). The `custom_id` carries your record's key through *any* number of batches, so results always map back.

```php
$group = Atlas::batchGroup('caption-run');

Image::whereNull('caption')->chunkById(1000, function ($images) use ($group) {
    $batch = Atlas::batch('openai')->group($group); // groups require persistence

    foreach ($images as $image) {
        $batch->add(
            Atlas::text()->model('gpt-5')->message('Caption this image.', [Image::fromUrl($image->url)]),
            key: (string) $image->id,
        );
    }

    $batch->submit();
});

$group->progress();   // ['total' => 4000, 'succeeded' => 3820, ... 'completed_jobs' => 3]
$group->isComplete(); // true once every job in the group is terminal
```

## Where results are stored

With tracking on, each line becomes a row in `batch_results` (keyed by `custom_id`), and the job's rolled-up token usage lands on the `batch_jobs` row — so batch spend shows up in your cost reporting. Text and embedding payloads are stored as JSON; the provider's raw output file is consumed during ingestion and discarded.

## Retention

Batch history is kept for `atlas.batch.retention_days` (default **90**). Schedule the prune command daily to keep the tables bounded as they grow:

```php
Schedule::command('atlas:batch-prune')->daily();
```

## Commands

| Command | Purpose |
|---------|---------|
| `atlas:batch-poll` | Advance open jobs and hydrate completed ones. Schedule every few minutes. |
| `atlas:batch-prune` | Delete jobs, their results, and empty groups older than the retention window. Schedule daily. |
