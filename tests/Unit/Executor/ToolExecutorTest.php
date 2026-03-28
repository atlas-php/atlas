<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\ToolNotFoundException;
use Atlasphp\Atlas\Executor\ToolExecutor;
use Atlasphp\Atlas\Executor\ToolRegistry;
use Atlasphp\Atlas\Executor\ToolResult;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Tools\Tool;

it('resolves tool, calls handle, serializes result', function () {
    $tool = new class extends Tool
    {
        public function name(): string
        {
            return 'greet';
        }

        public function description(): string
        {
            return 'Greets someone.';
        }

        public function handle(array $args, array $context): mixed
        {
            return 'Hello, '.$args['name'];
        }
    };

    $registry = new ToolRegistry([$tool]);
    $executor = new ToolExecutor($registry);

    $toolCall = new ToolCall('tc-1', 'greet', ['name' => 'Tim']);
    $result = $executor->execute($toolCall, []);

    expect($result)->toBeInstanceOf(ToolResult::class);
    expect($result->content)->toBe('Hello, Tim');
    expect($result->isError)->toBeFalse();
    expect($result->toolCall)->toBe($toolCall);
});

it('passes meta through to handle', function () {
    $tool = new class extends Tool
    {
        public function name(): string
        {
            return 'context_check';
        }

        public function description(): string
        {
            return 'Returns meta value.';
        }

        public function handle(array $args, array $context): mixed
        {
            return $context['user_id'];
        }
    };

    $registry = new ToolRegistry([$tool]);
    $executor = new ToolExecutor($registry);

    $result = $executor->execute(
        new ToolCall('tc-1', 'context_check', []),
        ['user_id' => 99],
    );

    expect($result->content)->toBe('99');
});

it('serializes array results to json', function () {
    $tool = new class extends Tool
    {
        public function name(): string
        {
            return 'list_items';
        }

        public function description(): string
        {
            return 'Lists items.';
        }

        public function handle(array $args, array $context): mixed
        {
            return ['a', 'b', 'c'];
        }
    };

    $registry = new ToolRegistry([$tool]);
    $executor = new ToolExecutor($registry);

    $result = $executor->execute(new ToolCall('tc-1', 'list_items', []), []);

    expect($result->content)->toBe('["a","b","c"]');
});

it('throws ToolNotFoundException for unknown tool', function () {
    $registry = new ToolRegistry([]);
    $executor = new ToolExecutor($registry);

    $executor->execute(new ToolCall('tc-1', 'unknown', []), []);
})->throws(ToolNotFoundException::class, 'Tool [unknown] is not registered.');

it('does not catch handle exceptions', function () {
    $tool = new class extends Tool
    {
        public function name(): string
        {
            return 'exploder';
        }

        public function description(): string
        {
            return 'Always fails.';
        }

        public function handle(array $args, array $context): mixed
        {
            throw new InvalidArgumentException('Boom!');
        }
    };

    $registry = new ToolRegistry([$tool]);
    $executor = new ToolExecutor($registry);

    $executor->execute(new ToolCall('tc-1', 'exploder', []), []);
})->throws(InvalidArgumentException::class, 'Boom!');
