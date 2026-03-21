<?php

declare(strict_types=1);

use Atlasphp\Atlas\Concerns\HasVariables;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Support\VariableRegistry;

class HasVariablesTestHelper
{
    use HasMeta;
    use HasVariables;

    public function testInterpolate(?string $template): ?string
    {
        return $this->interpolate($template);
    }

    public function testResolveVariables(): array
    {
        return $this->resolveVariables();
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getInterpolateMessages(): bool
    {
        return $this->interpolateMessages;
    }
}

it('withVariables stores values and returns static', function () {
    $helper = new HasVariablesTestHelper;

    $result = $helper->withVariables(['NAME' => 'Tim']);

    expect($result)->toBe($helper);
    expect($helper->getVariables())->toBe(['NAME' => 'Tim']);
});

it('withVariables merges recursively on multiple calls', function () {
    $helper = new HasVariablesTestHelper;

    $helper->withVariables(['COMPANY' => ['NAME' => 'Acme']]);
    $helper->withVariables(['COMPANY' => ['URL' => 'https://acme.com']]);

    expect($helper->getVariables())->toBe([
        'COMPANY' => ['NAME' => 'Acme', 'URL' => 'https://acme.com'],
    ]);
});

it('withMessageInterpolation sets flag and returns static', function () {
    $helper = new HasVariablesTestHelper;

    $result = $helper->withMessageInterpolation();

    expect($result)->toBe($helper);
    expect($helper->getInterpolateMessages())->toBeTrue();
});

it('withMessageInterpolation can disable', function () {
    $helper = new HasVariablesTestHelper;
    $helper->withMessageInterpolation();
    $helper->withMessageInterpolation(false);

    expect($helper->getInterpolateMessages())->toBeFalse();
});

it('interpolate returns null for null input', function () {
    $helper = new HasVariablesTestHelper;

    expect($helper->testInterpolate(null))->toBeNull();
});

it('interpolate short-circuits when no placeholders', function () {
    $helper = new HasVariablesTestHelper;

    expect($helper->testInterpolate('no placeholders here'))->toBe('no placeholders here');
});

it('interpolate resolves variables from all layers', function () {
    config(['atlas.variables' => ['BRAND' => 'TestBrand']]);

    $registry = app(VariableRegistry::class);
    $registry->register('GREETING', 'Hello');

    $helper = new HasVariablesTestHelper;
    $helper->withVariables(['USER' => 'Tim']);

    $result = $helper->testInterpolate('{GREETING} {USER}, welcome to {BRAND}');

    expect($result)->toBe('Hello Tim, welcome to TestBrand');
});

it('resolveVariables merges all three layers', function () {
    config(['atlas.variables' => ['A' => 'config']]);

    $registry = app(VariableRegistry::class);
    $registry->register('B', 'registry');

    $helper = new HasVariablesTestHelper;
    $helper->withVariables(['C' => 'runtime']);

    $resolved = $helper->testResolveVariables();

    expect($resolved['A'])->toBe('config');
    expect($resolved['B'])->toBe('registry');
    expect($resolved['C'])->toBe('runtime');
});

it('meta flows to closure variables', function () {
    $registry = app(VariableRegistry::class);
    $registry->register('USER_NAME', fn (array $meta) => $meta['user_name'] ?? 'Guest');

    $helper = new HasVariablesTestHelper;
    $helper->withMeta(['user_name' => 'Tim']);

    $result = $helper->testInterpolate('Hello {USER_NAME}');

    expect($result)->toBe('Hello Tim');
});
