<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Memory\MemoryContext;
use Atlasphp\Atlas\Persistence\Memory\MemoryModelService;
use Atlasphp\Atlas\Persistence\Memory\Tools\MemorySearch;
use Atlasphp\Atlas\Persistence\Models\Memory;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->memoryContext = app(MemoryContext::class);
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

it('has name search_memory', function () {
    $tool = app(MemorySearch::class);

    expect($tool->name())->toBe('search_memory');
});

it('has a description', function () {
    $tool = app(MemorySearch::class);

    expect($tool->description())->not->toBeEmpty();
});

it('defines required query parameter', function () {
    $tool = app(MemorySearch::class);
    $params = $tool->parameters();

    $names = array_map(fn ($p) => $p->name(), $params);
    expect($names)->toContain('query');
});

it('defines optional namespace, type, limit parameters', function () {
    $tool = app(MemorySearch::class);
    $params = $tool->parameters();

    $names = array_map(fn ($p) => $p->name(), $params);
    expect($names)->toContain('namespace')
        ->and($names)->toContain('type')
        ->and($names)->toContain('limit');
});

it('delegates to service search with context owner and agent', function () {
    $this->memoryContext->configure($this->owner, 'test-agent');

    $mockService = Mockery::mock(MemoryModelService::class);
    $mockService->shouldReceive('search')
        ->withArgs(function ($owner, $query, $type, $namespace, $agent, $minSimilarity, $limit) {
            return $owner === $this->owner
                && $query === 'search query'
                && $type === 'fact'
                && $namespace === 'work'
                && $agent === 'test-agent'
                && $limit === 10;
        })
        ->once()
        ->andReturn(collect([
            Memory::factory()->create([
                'content' => 'Found memory',
                'type' => 'fact',
                'namespace' => 'work',
                'importance' => 0.8,
            ]),
        ]));

    $tool = new MemorySearch($mockService, $this->memoryContext);

    $result = $tool->handle([
        'query' => 'search query',
        'type' => 'fact',
        'namespace' => 'work',
    ], []);

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0]['content'])->toBe('Found memory')
        ->and($result[0]['type'])->toBe('fact')
        ->and($result[0]['namespace'])->toBe('work')
        ->and($result[0]['importance'])->toBe(0.8)
        ->and($result[0])->toHaveKey('created_at');
});

it('returns no results message when search finds nothing', function () {
    $this->memoryContext->configure($this->owner, 'agent');

    $mockService = Mockery::mock(MemoryModelService::class);
    $mockService->shouldReceive('search')
        ->once()
        ->andReturn(collect());

    $tool = new MemorySearch($mockService, $this->memoryContext);

    $result = $tool->handle(['query' => 'nonexistent'], []);

    expect($result)->toBe('No relevant memories found.');
});

it('uses default limit of 10 when not specified', function () {
    $this->memoryContext->configure($this->owner, 'agent');

    $mockService = Mockery::mock(MemoryModelService::class);
    $mockService->shouldReceive('search')
        ->once()
        ->withArgs(fn ($owner, $query, $type, $namespace, $agent, $minSimilarity, $limit) => $limit === 10)
        ->andReturn(collect());

    $tool = new MemorySearch($mockService, $this->memoryContext);

    $tool->handle(['query' => 'test'], []);
});

it('passes custom limit from args', function () {
    $this->memoryContext->configure($this->owner, 'agent');

    $mockService = Mockery::mock(MemoryModelService::class);
    $mockService->shouldReceive('search')
        ->once()
        ->withArgs(fn ($owner, $query, $type, $namespace, $agent, $minSimilarity, $limit) => $limit === 5)
        ->andReturn(collect());

    $tool = new MemorySearch($mockService, $this->memoryContext);

    $tool->handle(['query' => 'test', 'limit' => 5], []);
});

it('uses null owner when context has no owner', function () {
    $this->memoryContext->configure(null, 'agent');

    $mockService = Mockery::mock(MemoryModelService::class);
    $mockService->shouldReceive('search')
        ->once()
        ->withArgs(fn ($owner) => $owner === null)
        ->andReturn(collect());

    $tool = new MemorySearch($mockService, $this->memoryContext);

    $tool->handle(['query' => 'test'], []);
});

it('produces valid tool definition', function () {
    $tool = app(MemorySearch::class);
    $definition = $tool->toDefinition();

    expect($definition->name)->toBe('search_memory')
        ->and($definition->parameters['properties'])->toHaveKey('query')
        ->and($definition->parameters['required'])->toContain('query');
});
