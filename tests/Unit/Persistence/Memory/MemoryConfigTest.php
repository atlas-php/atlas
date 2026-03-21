<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Memory\MemoryConfig;

it('make returns a MemoryConfig instance', function () {
    expect(MemoryConfig::make())->toBeInstanceOf(MemoryConfig::class);
});

it('has no tools enabled by default', function () {
    $config = MemoryConfig::make();

    expect($config->hasSearchTool())->toBeFalse()
        ->and($config->hasRecallTool())->toBeFalse()
        ->and($config->hasRememberTool())->toBeFalse();
});

it('has no variable documents by default', function () {
    $config = MemoryConfig::make();

    expect($config->getVariableDocuments())->toBe([]);
});

it('variables sets document types', function () {
    $config = MemoryConfig::make()->variables(['soul', 'identity']);

    expect($config->getVariableDocuments())->toBe(['soul', 'identity']);
});

it('withSearch enables only search tool', function () {
    $config = MemoryConfig::make()->withSearch();

    expect($config->hasSearchTool())->toBeTrue()
        ->and($config->hasRecallTool())->toBeFalse()
        ->and($config->hasRememberTool())->toBeFalse();
});

it('withRecall enables only recall tool', function () {
    $config = MemoryConfig::make()->withRecall();

    expect($config->hasSearchTool())->toBeFalse()
        ->and($config->hasRecallTool())->toBeTrue()
        ->and($config->hasRememberTool())->toBeFalse();
});

it('withRemember enables only remember tool', function () {
    $config = MemoryConfig::make()->withRemember();

    expect($config->hasSearchTool())->toBeFalse()
        ->and($config->hasRecallTool())->toBeFalse()
        ->and($config->hasRememberTool())->toBeTrue();
});

it('withTools enables all three tools', function () {
    $config = MemoryConfig::make()->withTools();

    expect($config->hasSearchTool())->toBeTrue()
        ->and($config->hasRecallTool())->toBeTrue()
        ->and($config->hasRememberTool())->toBeTrue();
});

it('supports fluent chaining', function () {
    $config = MemoryConfig::make()
        ->variables(['soul'])
        ->withSearch()
        ->withRemember();

    expect($config->getVariableDocuments())->toBe(['soul'])
        ->and($config->hasSearchTool())->toBeTrue()
        ->and($config->hasRecallTool())->toBeFalse()
        ->and($config->hasRememberTool())->toBeTrue();
});
