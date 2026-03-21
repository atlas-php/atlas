<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\SimilaritySearch;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Database\Eloquent\Model;

it('has default name similarity_search', function () {
    $tool = new SimilaritySearch;

    expect($tool->name())->toBe('similarity_search');
});

it('overrides name with withName', function () {
    $tool = (new SimilaritySearch)->withName('search_docs');

    expect($tool->name())->toBe('search_docs');
});

it('has a sensible default description', function () {
    $tool = new SimilaritySearch;

    expect($tool->description())->toBe('Search for similar content using semantic similarity.');
});

it('overrides description with withDescription', function () {
    $tool = (new SimilaritySearch)->withDescription('Search internal documentation.');

    expect($tool->description())->toBe('Search internal documentation.');
});

it('returns query parameter in parameters', function () {
    $tool = new SimilaritySearch;
    $params = $tool->parameters();

    expect($params)->toHaveCount(1);
    expect($params[0]->name())->toBe('query');
});

it('invokes the using callback with query argument', function () {
    $received = null;

    $tool = new SimilaritySearch(using: function (string $query) use (&$received) {
        $received = $query;

        return ['result1', 'result2'];
    });

    $result = $tool->handle(['query' => 'best practices'], []);

    expect($received)->toBe('best practices');
    expect($result)->toBe(['result1', 'result2']);
});

it('throws RuntimeException when no callback provided', function () {
    $tool = new SimilaritySearch;

    $tool->handle(['query' => 'test'], []);
})->throws(RuntimeException::class, 'No search callback provided');

it('produces valid ToolDefinition', function () {
    $tool = (new SimilaritySearch(using: fn (string $q) => []))
        ->withName('search_faq')
        ->withDescription('Search FAQ entries.');

    $definition = $tool->toDefinition();

    expect($definition)->toBeInstanceOf(ToolDefinition::class);
    expect($definition->name)->toBe('search_faq');
    expect($definition->description)->toBe('Search FAQ entries.');
    expect($definition->parameters['properties'])->toHaveKey('query');
    expect($definition->parameters['required'])->toBe(['query']);
});

it('creates tool from usingModel factory', function () {
    // We can't test the full model query without a DB, but we can verify
    // the factory sets description correctly and returns a SimilaritySearch instance
    $tool = SimilaritySearch::usingModel(
        Model::class,
        'embedding',
    )->withName('search_models');

    expect($tool)->toBeInstanceOf(SimilaritySearch::class);
    expect($tool->name())->toBe('search_models');
    expect($tool->description())->toContain('Model');
});
