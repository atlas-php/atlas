<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Memory\MemoryBuilder;
use Atlasphp\Atlas\Persistence\Memory\MemoryService;
use Atlasphp\Atlas\Persistence\Models\Memory;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->service = app(MemoryService::class);
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

    $result = $this->builder->forget(id: $memory->id);

    expect($result)->toBeTrue()
        ->and(Memory::count())->toBe(0);
});

it('forget by criteria delegates to service with scoped params', function () {
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

    $deleted = $this->builder->for($this->owner)->forget(type: 'note');

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
