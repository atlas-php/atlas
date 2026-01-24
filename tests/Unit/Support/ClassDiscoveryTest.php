<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Support\ClassDiscovery;
use Atlasphp\Atlas\Tools\Contracts\ToolContract;

beforeEach(function () {
    $this->discovery = new ClassDiscovery;
});

test('it returns empty array when directory does not exist', function () {
    $result = $this->discovery->discover('/non/existent/path', 'NonExistent', AgentContract::class);

    expect($result)->toBe([]);
});

test('it discovers agent classes from fixtures', function () {
    $result = $this->discovery->discover(
        __DIR__.'/../../Fixtures',
        'Atlasphp\\Atlas\\Tests\\Fixtures',
        AgentContract::class,
    );

    expect($result)->toContain('Atlasphp\\Atlas\\Tests\\Fixtures\\TestAgent');
    expect($result)->toContain('Atlasphp\\Atlas\\Tests\\Fixtures\\TestAgentWithDefaults');
});

test('it discovers tool classes from fixtures', function () {
    $result = $this->discovery->discover(
        __DIR__.'/../../Fixtures',
        'Atlasphp\\Atlas\\Tests\\Fixtures',
        ToolContract::class,
    );

    expect($result)->toContain('Atlasphp\\Atlas\\Tests\\Fixtures\\TestTool');
    expect($result)->toContain('Atlasphp\\Atlas\\Tests\\Fixtures\\RawToolContract');
});

test('it ignores abstract classes and interfaces', function () {
    // The Fixtures directory contains some interfaces/abstract classes
    // They should not appear in results
    $result = $this->discovery->discover(
        __DIR__.'/../../Fixtures',
        'Atlasphp\\Atlas\\Tests\\Fixtures',
        AgentContract::class,
    );

    // Only concrete agent implementations should be returned
    foreach ($result as $class) {
        $reflection = new ReflectionClass($class);
        expect($reflection->isAbstract())->toBeFalse();
        expect($reflection->isInterface())->toBeFalse();
    }
});

test('it returns empty array when no classes implement interface', function () {
    // Create a temp directory with no matching classes
    $tempDir = sys_get_temp_dir().'/atlas-test-'.uniqid();
    mkdir($tempDir);

    try {
        $result = $this->discovery->discover($tempDir, 'TempNamespace', AgentContract::class);
        expect($result)->toBe([]);
    } finally {
        rmdir($tempDir);
    }
});

test('it infers class names using PSR-4 conventions', function () {
    // This test verifies the class name inference works correctly
    // by checking that discovered classes match the expected pattern
    $result = $this->discovery->discover(
        __DIR__.'/../../Fixtures',
        'Atlasphp\\Atlas\\Tests\\Fixtures',
        AgentContract::class,
    );

    foreach ($result as $class) {
        // All discovered classes should start with the base namespace
        expect($class)->toStartWith('Atlasphp\\Atlas\\Tests\\Fixtures\\');

        // And should be loadable
        expect(class_exists($class))->toBeTrue();
    }
});
