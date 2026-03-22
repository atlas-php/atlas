<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Memory\MemoryBuilder;
use Atlasphp\Atlas\Persistence\Memory\MemoryModelService;
use Atlasphp\Atlas\Persistence\Models\Memory;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->service = app(MemoryModelService::class);
    $this->builder = new MemoryBuilder($this->service);
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

it('for() returns a cloned instance with owner set', function () {
    $scoped = $this->builder->for($this->owner);

    expect($scoped)->not->toBe($this->builder);
});

it('agent() returns a cloned instance with agent set', function () {
    $scoped = $this->builder->agent('support');

    expect($scoped)->not->toBe($this->builder);
});

it('namespace() returns a cloned instance with namespace set', function () {
    $scoped = $this->builder->namespace('work');

    expect($scoped)->not->toBe($this->builder);
});

it('remember delegates to service with scoped owner', function () {
    $memory = $this->builder->for($this->owner)->remember('Test content');

    expect($memory)->toBeInstanceOf(Memory::class)
        ->and($memory->memoryable_type)->toBe('App\\Models\\User')
        ->and($memory->memoryable_id)->toBe(42);
});

it('remember uses scoped agent', function () {
    $memory = $this->builder->agent('support')->remember('Test content');

    expect($memory->agent)->toBe('support');
});

it('remember uses scoped namespace as fallback', function () {
    $memory = $this->builder->namespace('work')->remember('Test content');

    expect($memory->namespace)->toBe('work');
});

it('remember explicit namespace overrides scoped namespace', function () {
    $memory = $this->builder->namespace('work')->remember('Test', namespace: 'personal');

    expect($memory->namespace)->toBe('personal');
});

it('forget by id delegates to service', function () {
    $memory = Memory::factory()->create();

    $result = $this->builder->forget($memory->id);

    expect($result)->toBeTrue()
        ->and(Memory::count())->toBe(0);
});

it('forgetWhere deletes by criteria with scoped params', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'note',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'fact',
    ]);

    $deleted = $this->builder->for($this->owner)->forgetWhere(type: 'note');

    expect($deleted)->toBe(1)
        ->and(Memory::count())->toBe(1);
});

it('forgetExpired delegates to service', function () {
    Memory::factory()->expired()->create();
    Memory::factory()->create();

    $deleted = $this->builder->forgetExpired();

    expect($deleted)->toBe(1)
        ->and(Memory::count())->toBe(1);
});

it('recall delegates to service with scoped params', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'profile',
        'content' => 'Test profile',
    ]);

    $result = $this->builder->for($this->owner)->recall('profile');

    expect($result)->not->toBeNull()
        ->and($result->content)->toBe('Test profile');
});

it('recallMany delegates to service', function () {
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

    $results = $this->builder->for($this->owner)->recallMany(['profile', 'context']);

    expect($results)->toHaveCount(2);
});

it('query returns a scoped builder with agent and namespace', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'agent' => 'support',
        'namespace' => 'work',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'agent' => 'sales',
        'namespace' => 'work',
    ]);

    $results = $this->builder
        ->for($this->owner)
        ->agent('support')
        ->namespace('work')
        ->query()
        ->get();

    expect($results)->toHaveCount(1);
});

it('forget returns false for non-existent ID', function () {
    $result = $this->builder->forget(9999);

    expect($result)->toBeFalse();
});

it('forgetWhere uses scoped namespace as fallback', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'namespace' => 'work',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'namespace' => 'personal',
    ]);

    $deleted = $this->builder->for($this->owner)->namespace('work')->forgetWhere();

    expect($deleted)->toBe(1)
        ->and(Memory::count())->toBe(1)
        ->and(Memory::first()->namespace)->toBe('personal');
});

it('forgetWhere explicit namespace overrides scoped namespace', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'namespace' => 'explicit',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'namespace' => 'scoped',
    ]);

    $deleted = $this->builder->for($this->owner)->namespace('scoped')->forgetWhere(namespace: 'explicit');

    expect($deleted)->toBe(1)
        ->and(Memory::count())->toBe(1)
        ->and(Memory::first()->namespace)->toBe('scoped');
});

it('forgetWhere uses scoped agent', function () {
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

    $deleted = $this->builder->for($this->owner)->agent('support')->forgetWhere();

    expect($deleted)->toBe(1)
        ->and(Memory::count())->toBe(1);
});

it('forgetWhere filters by key', function () {
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

    $deleted = $this->builder->for($this->owner)->forgetWhere(key: 'target');

    expect($deleted)->toBe(1)
        ->and(Memory::first()->key)->toBe('keep');
});

it('recall with key delegates to service', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'doc',
        'key' => 'intro',
        'content' => 'Intro',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'doc',
        'key' => 'outro',
        'content' => 'Outro',
    ]);

    $result = $this->builder->for($this->owner)->recall('doc', key: 'intro');

    expect($result)->not->toBeNull()
        ->and($result->content)->toBe('Intro');
});

it('recall returns null when not found', function () {
    $result = $this->builder->for($this->owner)->recall('nonexistent');

    expect($result)->toBeNull();
});

it('search delegates to service with scoped params', function () {
    // search() requires pgvector (whereVectorSimilarTo), so we mock the service
    $mockService = Mockery::mock(MemoryModelService::class);
    $mockService->shouldReceive('search')
        ->with(
            Mockery::type(Model::class),  // owner
            'test query',                  // query
            'fact',                        // type
            'work',                        // namespace (from scoped)
            'support',                     // agent (from scoped)
            0.7,                           // minSimilarity
            5,                             // limit
        )
        ->once()
        ->andReturn(collect([
            (object) ['content' => 'result 1'],
        ]));

    $builder = new MemoryBuilder($mockService);

    $results = $builder
        ->for($this->owner)
        ->agent('support')
        ->namespace('work')
        ->search('test query', type: 'fact', minSimilarity: 0.7, limit: 5);

    expect($results)->toHaveCount(1);
});

it('search uses scoped namespace as fallback', function () {
    $mockService = Mockery::mock(MemoryModelService::class);
    $mockService->shouldReceive('search')
        ->withArgs(function ($owner, $query, $type, $namespace) {
            return $namespace === 'scoped-ns';
        })
        ->once()
        ->andReturn(collect());

    $builder = new MemoryBuilder($mockService);

    $builder->namespace('scoped-ns')->search('query');
});

it('search explicit namespace overrides scoped', function () {
    $mockService = Mockery::mock(MemoryModelService::class);
    $mockService->shouldReceive('search')
        ->withArgs(function ($owner, $query, $type, $namespace) {
            return $namespace === 'explicit-ns';
        })
        ->once()
        ->andReturn(collect());

    $builder = new MemoryBuilder($mockService);

    $builder->namespace('scoped-ns')->search('query', namespace: 'explicit-ns');
});

it('search passes default parameters', function () {
    $mockService = Mockery::mock(MemoryModelService::class);
    $mockService->shouldReceive('search')
        ->with(
            null,    // owner
            'q',     // query
            null,    // type
            null,    // namespace
            null,    // agent
            0.5,     // default minSimilarity
            10,      // default limit
        )
        ->once()
        ->andReturn(collect());

    $builder = new MemoryBuilder($mockService);

    $builder->search('q');
});

it('decay delegates to service', function () {
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'importance' => 1.0,
        'updated_at' => now()->subMonths(4),
    ]);

    $affected = $this->builder->for($this->owner)->decay(now()->subMonths(3));

    expect($affected)->toBe(1);
});
