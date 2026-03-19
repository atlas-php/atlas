<?php

declare(strict_types=1);

use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Tools\ToolDefinition;

class FakeCalculatorTool extends Tool
{
    public function name(): string
    {
        return 'calculator';
    }

    public function description(): string
    {
        return 'Adds two numbers.';
    }

    public function parameters(): array
    {
        return [
            Schema::number('a', 'First number'),
            Schema::number('b', 'Second number'),
        ];
    }

    public function handle(array $args, array $context): mixed
    {
        return $args['a'] + $args['b'];
    }
}

class FakeNoParamsTool extends Tool
{
    public function name(): string
    {
        return 'ping';
    }

    public function description(): string
    {
        return 'Pings the server.';
    }

    public function handle(array $args, array $context): mixed
    {
        return 'pong';
    }
}

it('returns name and description', function () {
    $tool = new FakeCalculatorTool;

    expect($tool->name())->toBe('calculator');
    expect($tool->description())->toBe('Adds two numbers.');
});

it('returns parameters as Field array', function () {
    $tool = new FakeCalculatorTool;

    expect($tool->parameters())->toHaveCount(2);
    expect($tool->parameters()[0]->name())->toBe('a');
    expect($tool->parameters()[1]->name())->toBe('b');
});

it('executes handle with args and context', function () {
    $tool = new FakeCalculatorTool;

    expect($tool->handle(['a' => 5, 'b' => 3], []))->toBe(8);
});

it('passes context to handle', function () {
    $tool = new class extends Tool
    {
        public function name(): string
        {
            return 'context_tool';
        }

        public function description(): string
        {
            return 'Returns context.';
        }

        public function handle(array $args, array $context): mixed
        {
            return $context['user_id'];
        }
    };

    expect($tool->handle([], ['user_id' => 42]))->toBe(42);
});

it('converts to ToolDefinition', function () {
    $definition = (new FakeCalculatorTool)->toDefinition();

    expect($definition)->toBeInstanceOf(ToolDefinition::class);
    expect($definition->name)->toBe('calculator');
    expect($definition->description)->toBe('Adds two numbers.');
});

it('builds correct parameter schema in definition', function () {
    $definition = (new FakeCalculatorTool)->toDefinition();

    expect($definition->parameters)->toBe([
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number', 'description' => 'First number'],
            'b' => ['type' => 'number', 'description' => 'Second number'],
        ],
        'required' => ['a', 'b'],
    ]);
});

it('returns empty parameters for parameterless tool', function () {
    $definition = (new FakeNoParamsTool)->toDefinition();

    expect($definition->parameters)->toBe([]);
});

it('excludes optional parameters from required', function () {
    $tool = new class extends Tool
    {
        public function name(): string
        {
            return 'search';
        }

        public function description(): string
        {
            return 'Search.';
        }

        public function parameters(): array
        {
            return [
                Schema::string('query', 'The query'),
                Schema::integer('limit', 'Max results')->optional(),
            ];
        }

        public function handle(array $args, array $context): mixed
        {
            return [];
        }
    };

    $params = $tool->toDefinition()->parameters;

    expect($params['required'])->toBe(['query']);
    expect($params['properties'])->toHaveCount(2);
});

it('handles nested object parameters', function () {
    $tool = new class extends Tool
    {
        public function name(): string
        {
            return 'create_contact';
        }

        public function description(): string
        {
            return 'Creates a contact.';
        }

        public function parameters(): array
        {
            return [
                Schema::string('name', 'Full name'),
                Schema::object('address', 'Address')
                    ->string('city', 'City')
                    ->string('zip', 'ZIP code'),
            ];
        }

        public function handle(array $args, array $context): mixed
        {
            return $args;
        }
    };

    $params = $tool->toDefinition()->parameters;

    expect($params['properties']['address'])->toBe([
        'type' => 'object',
        'description' => 'Address',
        'properties' => [
            'city' => ['type' => 'string', 'description' => 'City'],
            'zip' => ['type' => 'string', 'description' => 'ZIP code'],
        ],
        'required' => ['city', 'zip'],
    ]);
});

it('defaults parameters to empty array', function () {
    $tool = new FakeNoParamsTool;

    expect($tool->parameters())->toBe([]);
});
