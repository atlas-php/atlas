<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tools\ToolDefinition;

it('stores name, description, and parameters', function () {
    $params = ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]];
    $definition = new ToolDefinition('search', 'Search things', $params);

    expect($definition->name)->toBe('search');
    expect($definition->description)->toBe('Search things');
    expect($definition->parameters)->toBe($params);
});

it('accepts empty parameters', function () {
    $definition = new ToolDefinition('ping', 'Ping the server', []);

    expect($definition->parameters)->toBe([]);
});

it('returns true from hasParameters when parameters exist', function () {
    $definition = new ToolDefinition('search', 'Search', ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]]);

    expect($definition->hasParameters())->toBeTrue();
});

it('returns false from hasParameters when parameters are empty', function () {
    $definition = new ToolDefinition('ping', 'Ping', []);

    expect($definition->hasParameters())->toBeFalse();
});
