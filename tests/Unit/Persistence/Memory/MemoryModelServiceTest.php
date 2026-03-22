<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Memory\MemoryModelService;
use Atlasphp\Atlas\Persistence\Models\Memory;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->service = new MemoryModelService;
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

it('upserts document memory by replacing old version', function () {
    $this->service->remember(
        $this->owner, 'Version 1', type: 'profile', key: 'main'
    );

    $v2 = $this->service->remember(
        $this->owner, 'Version 2', type: 'profile', key: 'main'
    );

    // Only the latest version should be active
    expect(Memory::count())->toBe(1)
        ->and($v2->content)->toBe('Version 2')
        ->and(Memory::first()->content)->toBe('Version 2');
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

it('creates document memory on first call with key (no existing)', function () {
    $memory = $this->service->remember(
        $this->owner, 'First version', type: 'doc', key: 'intro'
    );

    expect(Memory::count())->toBe(1)
        ->and($memory->key)->toBe('intro')
        ->and($memory->content)->toBe('First version');
});

it('upsert is scoped by agent — different agents same key coexist', function () {
    $this->service->remember(
        $this->owner, 'Support profile', type: 'profile', key: 'main', agent: 'support'
    );
    $this->service->remember(
        $this->owner, 'Sales profile', type: 'profile', key: 'main', agent: 'sales'
    );

    expect(Memory::count())->toBe(2);

    // Upsert within the same agent scope
    $this->service->remember(
        $this->owner, 'Updated support', type: 'profile', key: 'main', agent: 'support'
    );

    expect(Memory::count())->toBe(2);
    expect(Memory::where('agent', 'support')->first()->content)->toBe('Updated support');
});

it('upsert is scoped by type — different types same key coexist', function () {
    $this->service->remember(
        $this->owner, 'Profile main', type: 'profile', key: 'main'
    );
    $this->service->remember(
        $this->owner, 'Config main', type: 'config', key: 'main'
    );

    expect(Memory::count())->toBe(2);
});

it('upsert is scoped by owner — different owners same key coexist', function () {
    $otherOwner = new class extends Model
    {
        protected $table = 'users';

        public function getMorphClass(): string
        {
            return 'App\\Models\\User';
        }

        public function getKey(): mixed
        {
            return 99;
        }
    };

    $this->service->remember($this->owner, 'Owner 42', type: 'doc', key: 'main');
    $this->service->remember($otherOwner, 'Owner 99', type: 'doc', key: 'main');

    expect(Memory::count())->toBe(2);
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

it('forgetFor with owner only deletes all for that owner', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 99,
    ]);

    $deleted = $this->service->forgetFor(owner: $this->owner);

    expect($deleted)->toBe(2)
        ->and(Memory::count())->toBe(1);
});

it('forgetFor with null owner deletes global memories', function () {
    Memory::factory()->create([
        'memoryable_type' => null,
        'memoryable_id' => null,
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
    ]);

    $deleted = $this->service->forgetFor(owner: null);

    expect($deleted)->toBe(1)
        ->and(Memory::count())->toBe(1);
});

it('forgetFor filters by key', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'key' => 'target',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'key' => 'keep',
    ]);

    $deleted = $this->service->forgetFor(owner: $this->owner, key: 'target');

    expect($deleted)->toBe(1)
        ->and(Memory::count())->toBe(1)
        ->and(Memory::first()->key)->toBe('keep');
});

it('forgetFor filters by agent', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'agent' => 'support',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'agent' => 'sales',
    ]);

    $deleted = $this->service->forgetFor(owner: $this->owner, agent: 'support');

    expect($deleted)->toBe(1)
        ->and(Memory::count())->toBe(1)
        ->and(Memory::first()->agent)->toBe('sales');
});

it('forgetFor returns 0 when nothing matches', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'fact',
    ]);

    $deleted = $this->service->forgetFor(owner: $this->owner, type: 'nonexistent');

    expect($deleted)->toBe(0)
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
    // When agent is null, forAgent scope is not applied — returns the latest of all
    expect($result)->not->toBeNull()
        ->and($result->content)->toBe('Agent-specific');
});

it('recall filters by key', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'doc',
        'key' => 'intro',
        'content' => 'Intro content',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'doc',
        'key' => 'outro',
        'content' => 'Outro content',
    ]);

    $result = $this->service->recall($this->owner, 'doc', key: 'intro');

    expect($result)->not->toBeNull()
        ->and($result->content)->toBe('Intro content');
});

it('recall returns latest when multiple exist for same type', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'fact',
        'content' => 'First',
        'created_at' => now()->subHour(),
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'fact',
        'content' => 'Latest',
        'created_at' => now(),
    ]);

    $result = $this->service->recall($this->owner, 'fact');

    expect($result->content)->toBe('Latest');
});

it('recall global returns only ownerless memories', function () {
    Memory::factory()->create([
        'memoryable_type' => null,
        'memoryable_id' => null,
        'type' => 'setting',
        'content' => 'Global setting',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'setting',
        'content' => 'User setting',
    ]);

    $result = $this->service->recall(null, 'setting');

    expect($result)->not->toBeNull()
        ->and($result->content)->toBe('Global setting');
});

it('recall does not touch last_accessed_at when not found', function () {
    $result = $this->service->recall($this->owner, 'nonexistent');

    expect($result)->toBeNull();
});

it('recallMany filters by agent', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'profile',
        'agent' => 'support',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'profile',
        'agent' => 'sales',
    ]);

    $results = $this->service->recallMany($this->owner, ['profile'], agent: 'support');

    expect($results)->toHaveCount(1)
        ->and($results->first()->agent)->toBe('support');
});

it('recallMany returns empty collection when no matches', function () {
    $results = $this->service->recallMany($this->owner, ['nonexistent']);

    expect($results)->toBeEmpty();
});

it('recallMany does not touch when empty', function () {
    $results = $this->service->recallMany($this->owner, ['nonexistent']);

    expect($results)->toBeEmpty();
});

it('recallMany excludes expired memories', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'fact',
    ]);
    Memory::factory()->expired()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'fact',
    ]);

    $results = $this->service->recallMany($this->owner, ['fact']);

    expect($results)->toHaveCount(1);
});

it('recallMany global returns only ownerless memories', function () {
    Memory::factory()->create([
        'memoryable_type' => null,
        'memoryable_id' => null,
        'type' => 'setting',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'setting',
    ]);

    $results = $this->service->recallMany(null, ['setting']);

    expect($results)->toHaveCount(1)
        ->and($results->first()->memoryable_type)->toBeNull();
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

it('decay clamps factor above 1.0 to 1.0', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'importance' => 1.0,
    ]);

    Memory::query()->toBase()->update(['updated_at' => now()->subMonths(4)]);

    $this->service->decay($this->owner, now()->subMonths(3), 1.5);

    // Factor clamped to 1.0 — importance unchanged
    expect(Memory::first()->importance)->toBe(1.0);
});

it('decay clamps factor below 0.0 to 0.0', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'importance' => 0.8,
    ]);

    Memory::query()->toBase()->update(['updated_at' => now()->subMonths(4)]);

    $this->service->decay($this->owner, now()->subMonths(3), -0.5);

    // Factor clamped to 0.0 — importance zeroed
    expect(Memory::first()->importance)->toBe(0.0);
});

it('decay skips memories with zero importance', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'importance' => 0.0,
    ]);

    Memory::query()->toBase()->update(['updated_at' => now()->subMonths(4)]);

    $affected = $this->service->decay($this->owner, now()->subMonths(3), 0.5);

    expect($affected)->toBe(0);
});

it('decay without owner applies to all memories', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'importance' => 1.0,
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 99,
        'importance' => 1.0,
    ]);

    Memory::query()->toBase()->update(['updated_at' => now()->subMonths(4)]);

    $affected = $this->service->decay(null, now()->subMonths(3), 0.5);

    expect($affected)->toBe(2);
});

// ─── Model Resolution ───────────────────────────────────────

it('resolves model from config', function () {
    expect($this->service->resolveModel())->toBe(Memory::class);
});

it('resolves model from config override', function () {
    config(['atlas.persistence.models.memory' => 'App\\Models\\CustomMemory']);

    expect($this->service->resolveModel())->toBe('App\\Models\\CustomMemory');
});
