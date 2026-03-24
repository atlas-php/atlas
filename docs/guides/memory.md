# Memory

Atlas provides a persistence-backed memory system that agents and consumers can use to store and recall information across conversations. Memory is **consumer-managed** — Atlas provides the building blocks, you decide how to use them.

## Overview

The memory system consists of:

- **Memory model** — Eloquent model with polymorphic ownership, soft deletes, and optional vector embeddings
- **MemoryModelService** — CRUD, semantic search, recall, and maintenance operations
- **MemoryBuilder** — Fluent API via `Atlas::memory()` for consumer-side memory management
- **Memory tools** — Three agent-callable tools: `remember_memory`, `recall_memory`, `search_memory`
- **MemoryContext** — Scoped service that tells memory tools which owner and agent to operate against

## Atomic vs Document Memories

Atlas supports two memory patterns:

**Atomic** — Leave `key` null. Each call creates a new row. Use for accumulating facts, interaction history, or observations.

```php
Atlas::memory()->for($user)->remember('User prefers dark mode', type: 'preference');
Atlas::memory()->for($user)->remember('User is a Laravel developer', type: 'fact');
```

**Document** — Set a `key`. Soft-deletes the existing entry and creates a new one (version history preserved). Use for managed blocks like profile summaries or context snapshots.

```php
Atlas::memory()->for($user)->remember(
    'Tim is a senior developer working on Atlas and RocketQuote...',
    type: 'profile',
    key: 'main',
);
```

## Consumer API

The `Atlas::memory()` facade returns a fluent builder. Scoping methods return cloned instances for immutability.

### Scoping

```php
$memory = Atlas::memory()
    ->for($user)              // Scope to an owner (polymorphic)
    ->agent('support')        // Scope to a specific agent
    ->namespace('work');      // Scope to a namespace
```

### Remember

```php
// Atomic — creates a new row each time
Atlas::memory()->for($user)->remember('Likes coffee', type: 'preference');

// Document — upserts by key
Atlas::memory()->for($user)->remember(
    content: 'Updated profile summary...',
    type: 'profile',
    key: 'main',
    importance: 0.9,
    source: 'agent:support',
);
```

### Recall

```php
// Fetch by type (returns latest active memory)
$memory = Atlas::memory()->for($user)->recall('profile');

// Fetch by type + key
$memory = Atlas::memory()->for($user)->recall('profile', key: 'main');

// Fetch multiple types
$memories = Atlas::memory()->for($user)->recallMany(['preference', 'fact']);
```

### Search (Semantic)

Requires PostgreSQL with pgvector. Returns memories ranked by vector similarity.

```php
$results = Atlas::memory()
    ->for($user)
    ->search('contact preference', type: 'preference', limit: 5);
```

### Forget

```php
// By ID
Atlas::memory()->forget($memoryId);

// By criteria
Atlas::memory()->for($user)->forgetWhere(type: 'preference', namespace: 'old');

// Expired memories (force-deletes)
Atlas::memory()->forgetExpired();
```

### Maintenance

```php
// Decay importance of old memories
Atlas::memory()->for($user)->decay(
    olderThan: now()->subDays(30),
    factor: 0.8,
);
```

### Query (Escape Hatch)

```php
$builder = Atlas::memory()->for($user)->query();
$memories = $builder->where('importance', '>', 0.7)->get();
```

## Agent Tools

Atlas ships three memory tools that agents can call during execution. Register them like any other tool:

```php
use Atlasphp\Atlas\Persistence\Memory\Tools\MemoryRecall;
use Atlasphp\Atlas\Persistence\Memory\Tools\MemorySearch;
use Atlasphp\Atlas\Persistence\Memory\Tools\RememberMemory;

class SupportAgent extends Agent
{
    public function tools(): array
    {
        return [
            RememberMemory::class,
            MemoryRecall::class,
            MemorySearch::class,
        ];
    }
}
```

### Configuring MemoryContext

The memory tools use `MemoryContext` to know which owner and agent to operate against. Configure it before running the agent:

```php
use Atlasphp\Atlas\Persistence\Memory\MemoryContext;

// In a controller or service
app(MemoryContext::class)->configure($user, 'support');
$response = Atlas::agent('support')->ask('Hello');
```

For a reusable pattern, create middleware:

```php
class ConfigureMemory
{
    public function handle(AgentContext $context, Closure $next): mixed
    {
        $memoryContext = app(MemoryContext::class);
        $owner = Auth::user(); // or resolve from conversation
        $memoryContext->configure($owner, $context->agent?->key());

        try {
            return $next($context);
        } finally {
            $memoryContext->reset();
        }
    }
}
```

::: warning Queue & Octane
`MemoryContext` is registered as a scoped service — it resets per HTTP request automatically. In queue workers or Laravel Octane, call `reset()` manually after each job or request to prevent state leakage.
:::

### Tool Reference

| Tool | Name | Description |
|------|------|-------------|
| `RememberMemory` | `remember_memory` | Store information for future reference. Parameters: `content` (required), `type`, `namespace`, `key`, `importance` |
| `MemoryRecall` | `recall_memory` | Fetch a memory by exact type and optional key. Parameters: `type` (required), `key` |
| `MemorySearch` | `search_memory` | Semantic search for relevant memories. Parameters: `query` (required), `type`, `namespace`, `limit` |

## Injecting Memory into Prompts

Atlas does not automatically inject memory into agent instructions. To include memory content in prompts, recall it yourself and use [variables](/features/instructions):

```php
$profile = Atlas::memory()->for($user)->recall('profile');

$response = Atlas::agent('support')
    ->withVariables(['PROFILE' => $profile?->content ?? ''])
    ->ask('Hello');
```

Then reference it in your agent instructions:

```php
public function instructions(): string
{
    return <<<'PROMPT'
    You are a support agent.

    ## User Profile
    {PROFILE}
    PROMPT;
}
```

## Configuration

Memory requires persistence to be enabled:

```env
ATLAS_PERSISTENCE_ENABLED=true
ATLAS_MEMORY_AUTO_EMBED=true          # Auto-generate embeddings on save (PostgreSQL only)
ATLAS_EMBEDDING_DIMENSIONS=1536       # Vector dimensions for embeddings
```

The Memory model can be overridden in `config/atlas.php`:

```php
'persistence' => [
    'models' => [
        'memory' => \App\Models\CustomMemory::class,
    ],
],
```

## Schema

See [Persistence — Memories](/advanced/persistence#memories) for the full table schema and relationships.
