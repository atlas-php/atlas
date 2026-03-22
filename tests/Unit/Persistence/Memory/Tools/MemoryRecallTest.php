<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Memory\MemoryContext;
use Atlasphp\Atlas\Persistence\Memory\Tools\MemoryRecall;
use Atlasphp\Atlas\Persistence\Models\Memory;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->memoryContext = app(MemoryContext::class);
    $this->tool = app(MemoryRecall::class);
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

it('has name recall_memory', function () {
    expect($this->tool->name())->toBe('recall_memory');
});

it('has a description', function () {
    expect($this->tool->description())->not->toBeEmpty();
});

it('defines required type parameter', function () {
    $params = $this->tool->parameters();

    $names = array_map(fn ($p) => $p->name(), $params);
    expect($names)->toContain('type');
});

it('defines optional key parameter', function () {
    $params = $this->tool->parameters();

    $names = array_map(fn ($p) => $p->name(), $params);
    expect($names)->toContain('key');
});

it('recalls memory by type from context owner', function () {
    $this->memoryContext->configure($this->owner, 'test-agent');

    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'preference',
        'agent' => 'test-agent',
        'content' => 'Likes dark mode',
    ]);

    $result = $this->tool->handle(['type' => 'preference'], []);

    expect($result)->toBe('Likes dark mode');
});

it('recalls memory by type and key', function () {
    $this->memoryContext->configure($this->owner, 'agent');

    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'doc',
        'key' => 'intro',
        'agent' => 'agent',
        'content' => 'Intro content',
    ]);

    $result = $this->tool->handle(['type' => 'doc', 'key' => 'intro'], []);

    expect($result)->toBe('Intro content');
});

it('returns not found message when memory does not exist', function () {
    $this->memoryContext->configure($this->owner, 'agent');

    $result = $this->tool->handle(['type' => 'nonexistent'], []);

    expect($result)->toContain('No memory found')
        ->and($result)->toContain('nonexistent');
});

it('uses agent from context for scoping', function () {
    $this->memoryContext->configure($this->owner, 'support');

    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'note',
        'agent' => 'support',
        'content' => 'Support note',
    ]);
    Memory::factory()->create([
        'memoryable_type' => 'App\\Models\\User',
        'memoryable_id' => 42,
        'type' => 'note',
        'agent' => 'sales',
        'content' => 'Sales note',
    ]);

    $result = $this->tool->handle(['type' => 'note'], []);

    expect($result)->toBe('Support note');
});

it('recalls global memory when context has no owner', function () {
    $this->memoryContext->configure(null, null);

    Memory::factory()->create([
        'memoryable_type' => null,
        'memoryable_id' => null,
        'type' => 'global-fact',
        'agent' => null,
        'content' => 'Global memory',
    ]);

    $result = $this->tool->handle(['type' => 'global-fact'], []);

    expect($result)->toBe('Global memory');
});

it('produces valid tool definition', function () {
    $definition = $this->tool->toDefinition();

    expect($definition->name)->toBe('recall_memory')
        ->and($definition->parameters['properties'])->toHaveKey('type')
        ->and($definition->parameters['required'])->toContain('type');
});
