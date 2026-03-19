<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Tools\CodeInterpreter;
use Atlasphp\Atlas\Providers\Tools\FileSearch;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Providers\Tools\WebFetch;
use Atlasphp\Atlas\Providers\Tools\WebSearch;

it('all extend ProviderTool', function () {
    expect(new WebSearch)->toBeInstanceOf(ProviderTool::class);
    expect(new WebFetch)->toBeInstanceOf(ProviderTool::class);
    expect(new FileSearch)->toBeInstanceOf(ProviderTool::class);
    expect(new CodeInterpreter)->toBeInstanceOf(ProviderTool::class);
});

it('WebSearch has correct type', function () {
    expect((new WebSearch)->type())->toBe('web_search');
});

it('WebSearch includes config when provided', function () {
    $tool = new WebSearch(maxResults: 5, locale: 'en-US');

    expect($tool->toArray())->toBe([
        'type' => 'web_search',
        'max_results' => 5,
        'locale' => 'en-US',
    ]);
});

it('WebSearch omits config when empty', function () {
    expect((new WebSearch)->toArray())->toBe(['type' => 'web_search']);
});

it('WebFetch has correct type and minimal output', function () {
    $tool = new WebFetch;

    expect($tool->type())->toBe('web_fetch');
    expect($tool->toArray())->toBe(['type' => 'web_fetch']);
});

it('FileSearch includes stores when provided', function () {
    $tool = new FileSearch(stores: ['abc', 'def']);

    expect($tool->toArray())->toBe([
        'type' => 'file_search',
        'vector_store_ids' => ['abc', 'def'],
    ]);
});

it('FileSearch omits config when empty', function () {
    expect((new FileSearch)->toArray())->toBe(['type' => 'file_search']);
});

it('CodeInterpreter has correct type and minimal output', function () {
    $tool = new CodeInterpreter;

    expect($tool->type())->toBe('code_interpreter');
    expect($tool->toArray())->toBe(['type' => 'code_interpreter']);
});
