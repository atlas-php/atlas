<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Models\Memory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

it('creates a valid record via factory', function () {
    $memory = Memory::factory()->create();

    expect($memory)->toBeInstanceOf(Memory::class)
        ->and($memory->exists)->toBeTrue()
        ->and($memory->type)->toBe('fact')
        ->and($memory->content)->toBeString()
        ->and($memory->importance)->toBe(0.5);
});

it('casts attributes correctly', function () {
    $memory = Memory::factory()->create([
        'importance' => 0.8,
        'last_accessed_at' => now(),
        'expires_at' => now()->addDay(),
        'metadata' => ['key' => 'value'],
    ]);

    $memory->refresh();

    expect($memory->importance)->toBeFloat()
        ->and($memory->last_accessed_at)->toBeInstanceOf(Carbon::class)
        ->and($memory->expires_at)->toBeInstanceOf(Carbon::class)
        ->and($memory->metadata)->toBeArray()
        ->and($memory->metadata['key'])->toBe('value');
});

it('supports soft deletes', function () {
    $memory = Memory::factory()->create();

    $memory->delete();

    expect($memory->trashed())->toBeTrue()
        ->and(Memory::count())->toBe(0)
        ->and(Memory::withTrashed()->count())->toBe(1);

    $memory->restore();

    expect($memory->trashed())->toBeFalse()
        ->and(Memory::count())->toBe(1);
});

it('isAtomic returns true when key is null', function () {
    $memory = Memory::factory()->create(['key' => null]);

    expect($memory->isAtomic())->toBeTrue()
        ->and($memory->isDocument())->toBeFalse();
});

it('isDocument returns true when key is set', function () {
    $memory = Memory::factory()->document('main')->create();

    expect($memory->isDocument())->toBeTrue()
        ->and($memory->isAtomic())->toBeFalse();
});

it('isExpired returns true when expires_at is in the past', function () {
    $expired = Memory::factory()->expired()->create();
    $active = Memory::factory()->create(['expires_at' => now()->addDay()]);
    $noExpiry = Memory::factory()->create(['expires_at' => null]);

    expect($expired->isExpired())->toBeTrue()
        ->and($active->isExpired())->toBeFalse()
        ->and($noExpiry->isExpired())->toBeFalse();
});

it('scopeActive excludes expired memories', function () {
    Memory::factory()->create(); // no expiry — active
    Memory::factory()->create(['expires_at' => now()->addDay()]); // future — active
    Memory::factory()->expired()->create(); // expired

    expect(Memory::active()->count())->toBe(2);
});

it('scopeExpired returns only expired memories', function () {
    Memory::factory()->create(); // no expiry
    Memory::factory()->expired()->create();
    Memory::factory()->expired()->create();

    expect(Memory::expired()->count())->toBe(2);
});

it('scopeForOwner filters by polymorphic owner', function () {
    Memory::factory()->create(['memoryable_type' => 'App\\Models\\User', 'memoryable_id' => 1]);
    Memory::factory()->create(['memoryable_type' => 'App\\Models\\User', 'memoryable_id' => 2]);
    Memory::factory()->create(['memoryable_type' => null, 'memoryable_id' => null]);

    $owner = new class extends Model
    {
        protected $table = 'users';

        public function getMorphClass(): string
        {
            return 'App\\Models\\User';
        }

        public function getKey(): mixed
        {
            return 1;
        }
    };

    expect(Memory::forOwner($owner)->count())->toBe(1);
});

it('scopeGlobal returns only unscoped memories', function () {
    Memory::factory()->create(['memoryable_type' => null, 'memoryable_id' => null]);
    Memory::factory()->create(['memoryable_type' => null, 'memoryable_id' => null]);
    Memory::factory()->create(['memoryable_type' => 'App\\Models\\User', 'memoryable_id' => 1]);

    expect(Memory::global()->count())->toBe(2);
});

it('scopeForAgent filters by agent key', function () {
    Memory::factory()->forAgent('support')->create();
    Memory::factory()->forAgent('support')->create();
    Memory::factory()->forAgent('sales')->create();

    expect(Memory::forAgent('support')->count())->toBe(2)
        ->and(Memory::forAgent('sales')->count())->toBe(1);
});

it('scopeAgentAgnostic returns memories with null agent', function () {
    Memory::factory()->create(['agent' => null]);
    Memory::factory()->forAgent('support')->create();

    expect(Memory::agentAgnostic()->count())->toBe(1);
});

it('scopeOfType filters by type', function () {
    Memory::factory()->create(['type' => 'fact']);
    Memory::factory()->create(['type' => 'fact']);
    Memory::factory()->create(['type' => 'preference']);

    expect(Memory::ofType('fact')->count())->toBe(2)
        ->and(Memory::ofType('preference')->count())->toBe(1);
});

it('scopeInNamespace filters by namespace', function () {
    Memory::factory()->withNamespace('work')->create();
    Memory::factory()->withNamespace('personal')->create();
    Memory::factory()->create(['namespace' => null]);

    expect(Memory::inNamespace('work')->count())->toBe(1)
        ->and(Memory::inNamespace('personal')->count())->toBe(1);
});

it('embeddable returns correct config', function () {
    $memory = new Memory;

    expect($memory->embeddable())->toBe([
        'column' => 'embedding',
        'source' => 'content',
    ]);
});

it('shouldGenerateEmbedding respects memory_auto_embed config', function () {
    config(['atlas.persistence.memory_auto_embed' => false]);

    $memory = Memory::factory()->make(['content' => 'test']);
    $memory->syncOriginal();
    $memory->content = 'changed';

    expect($memory->shouldGenerateEmbedding())->toBeFalse();

    config(['atlas.persistence.memory_auto_embed' => true]);

    expect($memory->shouldGenerateEmbedding())->toBeTrue();
});

it('memoryable relationship is morphTo', function () {
    $memory = new Memory;

    expect($memory->memoryable())->toBeInstanceOf(MorphTo::class);
});

it('getTable does not double-prefix', function () {
    $memory = Memory::factory()->create();

    expect($memory->getTable())->toBe('atlas_memories')
        ->and($memory->getTable())->toBe('atlas_memories');
});
