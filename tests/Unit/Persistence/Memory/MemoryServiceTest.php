<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Memory\MemoryService;
use Atlasphp\Atlas\Persistence\Models\Memory;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->service = new MemoryService;
    $this->owner = new class extends Model
    {
        protected $table = 'users';

        public function getMorphClass(): string
        {
            return 'App\\Models\\User';
        }

        public function getKey(): mixed
        {
            return 42;
        }
    };
});

// ─── Remember ───────────────────────────────────────────────

it('creates an atomic memory when key is null', function () {
    $memory = $this->service->remember($this->owner, 'Prefers dark mode');

    expect($memory)->toBeInstanceOf(Memory::class)
        ->and($memory->content)->toBe('Prefers dark mode')
        ->and($memory->type)->toBe('atomic')
        ->and($memory->key)->toBeNull()
        ->and($memory->memoryable_type)->toBe('App\\Models\\User')
        ->and($memory->memoryable_id)->toBe(42);
});

it('creates multiple atomic memories without conflict', function () {
    $this->service->remember($this->owner, 'Fact one');
    $this->service->remember($this->owner, 'Fact two');

    expect(Memory::count())->toBe(2);
});

it('upserts document memory by soft-deleting old version', function () {
    $v1 = $this->service->remember(
        $this->owner, 'Version 1', type: 'profile', key: 'main'
    );

    $v2 = $this->service->remember(
        $this->owner, 'Version 2', type: 'profile', key: 'main'
    );

    expect(Memory::count())->toBe(1)
        ->and(Memory::withTrashed()->count())->toBe(2)
        ->and($v2->content)->toBe('Version 2')
        ->and(Memory::withTrashed()->find($v1->id)->trashed())->toBeTrue();
});

it('creates global memory when owner is null', function () {
    $memory = $this->service->remember(null, 'Global fact');

    expect($memory->memoryable_type)->toBeNull()
        ->and($memory->memoryable_id)->toBeNull();
});

it('stores all provided attributes', function () {
    $memory = $this->service->remember(
        $this->owner,
        'Test content',
        type: 'note',
        namespace: 'work',
        key: 'meeting',
        agent: 'support',
        importance: 0.9,
        source: 'manual',
        expiresAt: now()->addWeek(),
        metadata: ['category' => 'test'],
    );

    expect($memory->type)->toBe('note')
        ->and($memory->namespace)->toBe('work')
        ->and($memory->key)->toBe('meeting')
        ->and($memory->agent)->toBe('support')
        ->and($memory->importance)->toBe(0.9)
        ->and($memory->source)->toBe('manual')
        ->and($memory->expires_at)->not->toBeNull()
        ->and($memory->metadata)->toBe(['category' => 'test']);
});

// ─── Forget ─────────────────────────────────────────────────

it('soft-deletes a memory by ID', function () {
    $memory = Memory::factory()->create();

    $result = $this->service->forget($memory->id);

    expect($result)->toBeTrue()
        ->and(Memory::count())->toBe(0)
        ->and(Memory::withTrashed()->count())->toBe(1);
});

it('returns false when forgetting non-existent ID', function () {
    expect($this->service->forget(9999))->toBeFalse();
});

it('forgetFor deletes by criteria', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'note',
        'namespace' => 'scratch',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'note',
        'namespace' => 'scratch',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'fact',
    ]);

    $deleted = $this->service->forgetFor(
        owner: $this->owner,
        type: 'note',
        namespace: 'scratch',
    );

    expect($deleted)->toBe(2)
        ->and(Memory::count())->toBe(1);
});

it('forgetExpired force-deletes expired memories', function () {
    Memory::factory()->create(); // active
    Memory::factory()->expired()->create();
    Memory::factory()->expired()->create();

    $deleted = $this->service->forgetExpired();

    expect($deleted)->toBe(2)
        ->and(Memory::count())->toBe(1)
        ->and(Memory::withTrashed()->count())->toBe(1); // force delete, no trash
});

// ─── Recall ─────────────────────────────────────────────────

it('recalls a memory by type', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'profile',
        'content' => 'Test profile',
    ]);

    $memory = $this->service->recall($this->owner, 'profile');

    expect($memory)->not->toBeNull()
        ->and($memory->content)->toBe('Test profile');
});

it('recall touches last_accessed_at', function () {
    $memory = Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'profile',
        'last_accessed_at' => null,
    ]);

    $this->service->recall($this->owner, 'profile');

    $memory->refresh();
    expect($memory->last_accessed_at)->not->toBeNull();
});

it('recall returns null for missing type', function () {
    expect($this->service->recall($this->owner, 'nonexistent'))->toBeNull();
});

it('recall excludes expired memories', function () {
    Memory::factory()->expired()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'old',
    ]);

    expect($this->service->recall($this->owner, 'old'))->toBeNull();
});

it('recall filters by agent', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'note',
        'agent' => 'support',
        'content' => 'Agent-specific',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'note',
        'agent' => null,
        'content' => 'Agent-agnostic',
    ]);

    $result = $this->service->recall($this->owner, 'note', agent: 'support');
    expect($result->content)->toBe('Agent-specific');

    $result = $this->service->recall($this->owner, 'note', agent: null);
    // agent is null, but the query uses global() scoping, which returns where memoryable is null
    // Actually for recall, when agent is null it just doesn't filter by agent
    // Let me check - recall with null agent doesn't call forAgent scope
    // So it returns the latest of both. Let's verify:
    expect($result)->not->toBeNull();
});

it('recallMany fetches multiple types', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'profile',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'context',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'unrelated',
    ]);

    $results = $this->service->recallMany($this->owner, ['profile', 'context']);

    expect($results)->toHaveCount(2)
        ->and($results->pluck('type')->sort()->values()->toArray())
        ->toBe(['context', 'profile']);
});

it('recallMany touches last_accessed_at on all results', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'profile',
        'last_accessed_at' => null,
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'context',
        'last_accessed_at' => null,
    ]);

    $this->service->recallMany($this->owner, ['profile', 'context']);

    $memories = Memory::all();
    $memories->each(fn ($m) => expect($m->last_accessed_at)->not->toBeNull());
});

// ─── Query ──────────────────────────────────────────────────

it('query returns a scoped builder', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'fact',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 99,
        'type' => 'fact',
    ]);

    $results = $this->service->query($this->owner)->get();

    expect($results)->toHaveCount(1);
});

it('query without owner returns all active memories', function () {
    Memory::factory()->create();
    Memory::factory()->create();
    Memory::factory()->expired()->create();

    expect($this->service->query()->count())->toBe(2);
});

// ─── Maintenance ────────────────────────────────────────────

it('touch updates last_accessed_at', function () {
    $memory = Memory::factory()->create(['last_accessed_at' => null]);

    $this->service->touch($memory->id);

    $memory->refresh();
    expect($memory->last_accessed_at)->not->toBeNull();
});

it('decay multiplies importance by factor', function () {
    $m1 = Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'importance' => 1.0,
    ]);
    $m2 = Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'importance' => 0.5,
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'importance' => 1.0,
    ]);

    // Use query builder to bypass Eloquent timestamp auto-update
    Memory::whereIn('id', [$m1->id, $m2->id])
        ->toBase()
        ->update(['updated_at' => now()->subMonths(4)]);

    $affected = $this->service->decay($this->owner, now()->subMonths(3), 0.8);

    expect($affected)->toBe(2);

    $memories = Memory::orderBy('id')->get();
    expect($memories[0]->importance)->toBe(0.8)
        ->and($memories[1]->importance)->toBe(0.4)
        ->and($memories[2]->importance)->toBe(1.0);
});

// ─── Model Resolution ───────────────────────────────────────

it('resolves model from config', function () {
    expect($this->service->resolveModel())->toBe(Memory::class);
});
