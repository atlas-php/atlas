<?php

declare(strict_types=1);

use Atlasphp\Atlas\Support\VariableRegistry;

it('registers and resolves a static value', function () {
    $registry = new VariableRegistry;
    $registry->register('NAME', 'Tim');

    expect($registry->resolve())->toBe(['NAME' => 'Tim']);
});

it('registers and resolves a closure', function () {
    $registry = new VariableRegistry;
    $registry->register('DATE', fn () => '2026-03-21');

    expect($registry->resolve())->toBe(['DATE' => '2026-03-21']);
});

it('registers and resolves a nested array', function () {
    $registry = new VariableRegistry;
    $registry->register('COMPANY', ['NAME' => 'Acme', 'URL' => 'https://acme.com']);

    expect($registry->resolve())->toBe([
        'COMPANY' => ['NAME' => 'Acme', 'URL' => 'https://acme.com'],
    ]);
});

it('registers a flat dotted key', function () {
    $registry = new VariableRegistry;
    $registry->register('COMPANY.NAME', 'Acme');

    expect($registry->resolve())->toBe(['COMPANY.NAME' => 'Acme']);
});

it('registers many variables', function () {
    $registry = new VariableRegistry;
    $registry->registerMany(['A' => '1', 'B' => '2']);

    expect($registry->resolve())->toBe(['A' => '1', 'B' => '2']);
});

it('unregisters a variable', function () {
    $registry = new VariableRegistry;
    $registry->register('NAME', 'Tim');
    $registry->unregister('NAME');

    expect($registry->resolve())->toBe([]);
});

it('invokes closures with no params', function () {
    $registry = new VariableRegistry;
    $registry->register('COUNTER', fn () => 42);

    expect($registry->resolve()['COUNTER'])->toBe(42);
});

it('invokes closures with meta param', function () {
    $registry = new VariableRegistry;
    $registry->register('USER', fn (array $meta) => $meta['user_name'] ?? 'Guest');

    expect($registry->resolve(['user_name' => 'Tim'])['USER'])->toBe('Tim');
    expect($registry->resolve()['USER'])->toBe('Guest');
});

it('resolves nested array closures recursively', function () {
    $registry = new VariableRegistry;
    $registry->register('USER', [
        'NAME' => fn (array $meta) => $meta['name'] ?? 'Guest',
        'STATIC' => 'value',
    ]);

    $resolved = $registry->resolve(['name' => 'Tim']);

    expect($resolved['USER']['NAME'])->toBe('Tim');
    expect($resolved['USER']['STATIC'])->toBe('value');
});

it('merges config registry and runtime with correct priority', function () {
    $registry = new VariableRegistry;
    $registry->register('NAME', 'Registry');

    config(['atlas.variables' => ['NAME' => 'Config', 'ONLY_CONFIG' => 'yes']]);

    $merged = $registry->merge(['NAME' => 'Runtime'], []);

    expect($merged['NAME'])->toBe('Runtime');
    expect($merged['ONLY_CONFIG'])->toBe('yes');
});

it('merges nested arrays recursively', function () {
    $registry = new VariableRegistry;
    $registry->register('COMPANY', ['URL' => 'https://registry.com']);

    config(['atlas.variables' => ['COMPANY' => ['NAME' => 'Config Co', 'URL' => 'https://config.com']]]);

    $merged = $registry->merge(['COMPANY' => ['NAME' => 'Runtime Co']], []);

    expect($merged['COMPANY']['NAME'])->toBe('Runtime Co');
    expect($merged['COMPANY']['URL'])->toBe('https://registry.com');
});

it('returns config values when registry and runtime are empty', function () {
    $registry = new VariableRegistry;

    config(['atlas.variables' => ['APP_NAME' => 'TestApp']]);

    expect($registry->merge()['APP_NAME'])->toBe('TestApp');
});
