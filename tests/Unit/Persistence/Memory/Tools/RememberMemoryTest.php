<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Memory\MemoryContext;
use Atlasphp\Atlas\Persistence\Memory\Tools\RememberMemory;
use Atlasphp\Atlas\Persistence\Models\Memory;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->memoryContext = app(MemoryContext::class);
    $this->tool = app(RememberMemory::class);
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

it('has name remember_memory', function () {
    expect($this->tool->name())->toBe('remember_memory');
});

it('has a description', function () {
    expect($this->tool->description())->not->toBeEmpty();
});

it('defines required content parameter', function () {
    $params = $this->tool->parameters();

    $names = array_map(fn ($p) => $p->name(), $params);
    expect($names)->toContain('content');
});

it('defines optional type, namespace, key, importance parameters', function () {
    $params = $this->tool->parameters();

    $names = array_map(fn ($p) => $p->name(), $params);
    expect($names)->toContain('type')
        ->and($names)->toContain('namespace')
        ->and($names)->toContain('key')
        ->and($names)->toContain('importance');
});

it('stores memory with content from args', function () {
    $this->memoryContext->configure($this->owner, 'test-agent');

    $result = $this->tool->handle(['content' => 'User likes coffee'], []);

    expect($result)->toContain('Remembered');

    $memory = Memory::first();
    expect($memory)->not->toBeNull()
        ->and($memory->content)->toBe('User likes coffee')
        ->and($memory->memoryable_type)->toBe('App\\Models\\User')
        ->and($memory->memoryable_id)->toBe(42)
        ->and($memory->agent)->toBe('test-agent')
        ->and($memory->source)->toBe('tool:remember_memory');
});

it('uses default type atomic when not specified', function () {
    $this->memoryContext->configure($this->owner, 'agent');

    $this->tool->handle(['content' => 'Test'], []);

    expect(Memory::first()->type)->toBe('atomic');
});

it('uses provided type, namespace, key, importance', function () {
    $this->memoryContext->configure($this->owner, 'agent');

    $this->tool->handle([
        'content' => 'Test',
        'type' => 'preference',
        'namespace' => 'ui',
        'key' => 'theme',
        'importance' => 0.9,
    ], []);

    $memory = Memory::first();
    expect($memory->type)->toBe('preference')
        ->and($memory->namespace)->toBe('ui')
        ->and($memory->key)->toBe('theme')
        ->and($memory->importance)->toBe(0.9);
});

it('stores memory with null owner when context has no owner', function () {
    $this->memoryContext->configure(null, 'agent');

    $this->tool->handle(['content' => 'Global fact'], []);

    $memory = Memory::first();
    expect($memory->memoryable_type)->toBeNull()
        ->and($memory->memoryable_id)->toBeNull();
});

it('defaults importance to 0.5', function () {
    $this->memoryContext->configure($this->owner, 'agent');

    $this->tool->handle(['content' => 'Test'], []);

    expect(Memory::first()->importance)->toBe(0.5);
});

it('returns confirmation string with truncated content', function () {
    $this->memoryContext->configure($this->owner, 'agent');

    $longContent = str_repeat('A', 200);
    $result = $this->tool->handle(['content' => $longContent], []);

    expect($result)->toStartWith('Remembered: ')
        ->and(strlen($result))->toBeLessThan(200);
});

it('produces valid tool definition', function () {
    $definition = $this->tool->toDefinition();

    expect($definition->name)->toBe('remember_memory')
        ->and($definition->description)->not->toBeEmpty()
        ->and($definition->parameters['properties'])->toHaveKey('content')
        ->and($definition->parameters['required'])->toContain('content');
});
