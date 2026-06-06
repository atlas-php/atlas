<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Tools\CodeExecution;
use Atlasphp\Atlas\Providers\Tools\CodeInterpreter;
use Atlasphp\Atlas\Providers\Tools\FileSearch;
use Atlasphp\Atlas\Providers\Tools\GoogleSearch;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Providers\Tools\WebFetch;
use Atlasphp\Atlas\Providers\Tools\WebSearch;

it('all extend ProviderTool', function () {
    expect(new WebSearch)->toBeInstanceOf(ProviderTool::class);
    expect(new WebFetch)->toBeInstanceOf(ProviderTool::class);
    expect(new FileSearch)->toBeInstanceOf(ProviderTool::class);
    expect(new CodeInterpreter)->toBeInstanceOf(ProviderTool::class);
    expect(new GoogleSearch)->toBeInstanceOf(ProviderTool::class);
    expect(new CodeExecution)->toBeInstanceOf(ProviderTool::class);
});

it('WebSearch has correct type', function () {
    expect((new WebSearch)->type())->toBe('web_search');
});

it('WebSearch omits config when empty', function () {
    expect((new WebSearch)->toArray())->toBe(['type' => 'web_search']);
});

it('WebSearch includes domain scoping when provided', function () {
    $tool = new WebSearch(
        allowedDomains: ['laravel.com', 'php.net'],
        blockedDomains: ['spam.example'],
    );

    expect($tool->toArray())->toBe([
        'type' => 'web_search',
        'allowed_domains' => ['laravel.com', 'php.net'],
        'blocked_domains' => ['spam.example'],
    ]);
});

it('WebSearch omits empty domain arrays', function () {
    $tool = new WebSearch(allowedDomains: [], blockedDomains: []);

    expect($tool->toArray())->toBe(['type' => 'web_search']);
});

it('WebSearch accepts but ignores the deprecated maxResults/locale (no provider accepts them)', function () {
    $tool = new WebSearch(allowedDomains: ['laravel.com'], maxResults: 5, locale: 'en-US');

    expect($tool->toArray())->toBe([
        'type' => 'web_search',
        'allowed_domains' => ['laravel.com'],
    ]);
});

it('WebSearch merges custom options bag (forward-compatible attributes)', function () {
    $tool = new WebSearch(
        allowedDomains: ['laravel.com'],
        options: ['max_uses' => 5, 'search_context_size' => 'high'],
    );

    expect($tool->toArray())->toBe([
        'type' => 'web_search',
        'allowed_domains' => ['laravel.com'],
        'max_uses' => 5,
        'search_context_size' => 'high',
    ]);
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

it('FileSearch merges custom options bag', function () {
    $tool = new FileSearch(stores: ['vs_1'], options: ['ranking_options' => ['ranker' => 'auto']]);

    expect($tool->toArray())->toBe([
        'type' => 'file_search',
        'vector_store_ids' => ['vs_1'],
        'ranking_options' => ['ranker' => 'auto'],
    ]);
});

it('no-arg provider tools accept a custom options bag via the base constructor', function () {
    expect((new WebFetch(['max_uses' => 4]))->toArray())->toBe([
        'type' => 'web_fetch',
        'max_uses' => 4,
    ]);
});

it('CodeInterpreter defaults the container OpenAI requires', function () {
    $tool = new CodeInterpreter;

    expect($tool->type())->toBe('code_interpreter');
    expect($tool->toArray())->toBe([
        'type' => 'code_interpreter',
        'container' => ['type' => 'auto'],
    ]);
});

it('CodeInterpreter container can be overridden via the options bag', function () {
    $tool = new CodeInterpreter(['container' => 'cntr_123']);

    expect($tool->toArray())->toBe([
        'type' => 'code_interpreter',
        'container' => 'cntr_123',
    ]);
});

// ─── Google Provider Tools ──────────────────────────────────────────────────

it('GoogleSearch has correct type', function () {
    expect((new GoogleSearch)->type())->toBe('google_search');
});

it('GoogleSearch toArray uses base class pattern', function () {
    $tool = new GoogleSearch;

    expect($tool->toArray())->toBe(['type' => 'google_search']);
});

it('CodeExecution has correct type', function () {
    expect((new CodeExecution)->type())->toBe('code_execution');
});

it('CodeExecution toArray uses base class pattern', function () {
    $tool = new CodeExecution;

    expect($tool->toArray())->toBe(['type' => 'code_execution']);
});
