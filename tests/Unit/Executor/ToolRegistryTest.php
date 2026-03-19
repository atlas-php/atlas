<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\ToolNotFoundException;
use Atlasphp\Atlas\Executor\ToolRegistry;
use Atlasphp\Atlas\Tools\Tool;

function makeSearchTool(): Tool
{
    return new class extends Tool
    {
        public function name(): string
        {
            return 'search';
        }

        public function description(): string
        {
            return 'Searches things.';
        }

        public function handle(array $args, array $context): mixed
        {
            return 'results';
        }
    };
}

function makeCalcTool(): Tool
{
    return new class extends Tool
    {
        public function name(): string
        {
            return 'calculator';
        }

        public function description(): string
        {
            return 'Calculates things.';
        }

        public function handle(array $args, array $context): mixed
        {
            return $args['a'] + $args['b'];
        }
    };
}

it('indexes tools by name', function () {
    $search = makeSearchTool();
    $calc = makeCalcTool();

    $registry = new ToolRegistry([$search, $calc]);

    $all = $registry->all();
    expect($all)->toHaveCount(2);
    expect($all['search'])->toBe($search);
    expect($all['calculator'])->toBe($calc);
});

it('resolves a tool by name', function () {
    $search = makeSearchTool();
    $registry = new ToolRegistry([$search]);

    expect($registry->resolve('search'))->toBe($search);
});

it('throws ToolNotFoundException for unknown tool name', function () {
    $registry = new ToolRegistry([]);

    $registry->resolve('unknown');
})->throws(ToolNotFoundException::class, 'Tool [unknown] is not registered.');

it('returns true for has when tool exists', function () {
    $registry = new ToolRegistry([makeSearchTool()]);

    expect($registry->has('search'))->toBeTrue();
});

it('returns false for has when tool does not exist', function () {
    $registry = new ToolRegistry([makeSearchTool()]);

    expect($registry->has('nope'))->toBeFalse();
});

it('returns full map from all', function () {
    $search = makeSearchTool();
    $calc = makeCalcTool();
    $registry = new ToolRegistry([$search, $calc]);

    $all = $registry->all();

    expect($all)->toHaveCount(2);
    expect($all['search'])->toBe($search);
    expect($all['calculator'])->toBe($calc);
});
