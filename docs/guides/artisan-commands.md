# Artisan Commands

Atlas provides Artisan commands to scaffold agents and tools and to operate the chunked-embedding subsystem.

## make:agent

Generate a new agent class:

```bash
php artisan make:agent CustomerSupport
```

Creates `app/Agents/CustomerSupport.php`:

```php
use Atlasphp\Atlas\Agent;

class CustomerSupport extends Agent
{
    public function instructions(): ?string
    {
        return 'You are a helpful assistant.';
    }
}
```

### With Tools

```bash
php artisan make:agent CustomerSupport --tools
```

Includes a `tools()` method stub:

```php
class CustomerSupport extends Agent
{
    public function instructions(): ?string
    {
        return 'You are a helpful assistant.';
    }

    public function tools(): array
    {
        return [
            // \App\Tools\YourTool::class,
        ];
    }
}
```

### With Provider Tools

```bash
php artisan make:agent CustomerSupport --provider-tools
```

Includes a `providerTools()` method stub for native provider capabilities like web search.

### All Options Combined

```bash
php artisan make:agent CustomerSupport --tools --provider-tools
```

Includes both `tools()` and `providerTools()` methods.

### Options

| Option | Short | Description |
|--------|-------|-------------|
| `--tools` | `-t` | Include `tools()` method stub |
| `--provider-tools` | `-p` | Include `providerTools()` method stub |
| `--force` | `-f` | Overwrite if file already exists |

## make:tool

Generate a new tool class:

```bash
php artisan make:tool SearchProducts
```

Creates `app/Tools/SearchProducts.php`:

```php
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;

class SearchProducts extends Tool
{
    public function name(): string
    {
        return 'search_products';
    }

    public function description(): string
    {
        return 'TODO: Describe what this tool does.';
    }

    public function parameters(): array
    {
        return [
            // Schema::string('query', 'The search query'),
            // Schema::integer('limit', 'Max results to return')->optional(),
        ];
    }

    public function handle(array $args, array $context): mixed
    {
        // TODO: Implement your tool logic here.

        return 'Tool result';
    }
}
```

### Tool Name Derivation

The tool name is automatically generated as `snake_case` from the class name. A `Tool` suffix is stripped:

| Class Name | Generated Tool Name |
|---|---|
| `SearchProducts` | `search_products` |
| `LookupOrderTool` | `lookup_order` |
| `SendEmail` | `send_email` |

### Options

| Option | Short | Description |
|--------|-------|-------------|
| `--force` | `-f` | Overwrite if file already exists |

## atlas:chunk

Safety-net sweep for chunkable rows that bypassed dispatch-on-save (raw `DB::table()->update()`, mass seeds, queue outages, or consumers running with `dispatch_on_save = false`). Also prunes orphan chunks unless `--skip-orphans` is passed.

```bash
php artisan atlas:chunk
```

A row is "dirty" when its `content_hash` differs from `indexed_hash` and its `updated_at` is older than `atlas.embeddings.sweep_settle` seconds (default 60). Each dirty row dispatches one `ChunkContentJob` onto the configured queue. Rows that have hit `atlas.embeddings.max_failures` are excluded.

Recommended schedule:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('atlas:chunk')->hourly()->withoutOverlapping();
Schedule::command('atlas:prune-chunks')->daily()->withoutOverlapping();
```

With dispatch-on-save enabled (default), the trait's `saved` hook handles the hot path; `atlas:chunk` is just a backstop. If you keep the orphan scan here, you can drop `atlas:prune-chunks`; if you split them, pass `--skip-orphans` to `atlas:chunk`.

### Options

| Option | Description |
|--------|-------------|
| `--model={class}` | Only sweep the given fully-qualified model class. Default: every model registered via `Atlas::registerChunkable()`. |
| `--skip-orphans` | Skip the orphan-chunk purge — run `atlas:prune-chunks` separately. |

## atlas:prune-chunks

Delete chunk rows whose owner record no longer exists. Use this when you want the orphan scan on a slower cadence than `atlas:chunk` (it's the more expensive of the two — a full `atlas_chunks` scan per chunkable type).

```bash
php artisan atlas:prune-chunks
```

Recommended cadence: daily. Soft-deleted owners are not treated as orphans — their chunks survive for restore.

### Options

| Option | Description |
|--------|-------------|
| `--model={class}` | Only prune for the given chunkable class. Default: every registered chunkable model. |

## atlas:rechunk

Mark chunkable rows dirty so the next `atlas:chunk` sweep re-chunks them:

```bash
# Re-chunk every row of a class — use after deploying a new chunker or changing chunk_size.
php artisan atlas:rechunk "App\Models\Project"

# Re-chunk a single row by ID — use after investigating bad retrieval for one record.
php artisan atlas:rechunk "App\Models\Project" 42
```

Rows that previously hit `max_failures` are still skipped by the sweep. Pass `--reset-failures` to also clear `index_failure_count` and `last_index_error` so they're picked up again.

### Options

| Option | Description |
|--------|-------------|
| `--reset-failures` | Also clear `index_failure_count` and `last_index_error` so previously-skipped rows resume on the next sweep. |

## Auto-Discovery

Generated agents and tools are automatically discovered when auto-discovery is configured in `config/atlas.php`:

```php
'agents' => [
    'path' => app_path('Agents'),
    'namespace' => 'App\\Agents',
],
```

After scaffolding, use them immediately:

```php
// After: php artisan make:agent CustomerSupport
$response = Atlas::agent('customer-support')
    ->message('Hello')
    ->asText();
```
