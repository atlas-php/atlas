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

// === implementsInterface edge cases ===

test('implementsInterface returns false for non-existent class', function () {
    // Create a test subclass to access the protected method
    $discovery = new class extends ClassDiscovery
    {
        public function testImplementsInterface(string $class, string $interface): bool
        {
            return $this->implementsInterface($class, $interface);
        }
    };

    $result = $discovery->testImplementsInterface(
        'NonExistent\\Class\\That\\Does\\Not\\Exist',
        AgentContract::class
    );

    expect($result)->toBeFalse();
});

test('implementsInterface returns false for abstract classes', function () {
    $discovery = new class extends ClassDiscovery
    {
        public function testImplementsInterface(string $class, string $interface): bool
        {
            return $this->implementsInterface($class, $interface);
        }
    };

    // AgentDefinition is abstract
    $result = $discovery->testImplementsInterface(
        \Atlasphp\Atlas\Agents\AgentDefinition::class,
        AgentContract::class
    );

    expect($result)->toBeFalse();
});

test('implementsInterface returns false for interfaces', function () {
    $discovery = new class extends ClassDiscovery
    {
        public function testImplementsInterface(string $class, string $interface): bool
        {
            return $this->implementsInterface($class, $interface);
        }
    };

    // AgentContract is an interface
    $result = $discovery->testImplementsInterface(
        AgentContract::class,
        AgentContract::class
    );

    expect($result)->toBeFalse();
});

test('implementsInterface returns true for class implementing interface', function () {
    $discovery = new class extends ClassDiscovery
    {
        public function testImplementsInterface(string $class, string $interface): bool
        {
            return $this->implementsInterface($class, $interface);
        }
    };

    // TestAgent implements AgentContract
    $result = $discovery->testImplementsInterface(
        \Atlasphp\Atlas\Tests\Fixtures\TestAgent::class,
        AgentContract::class
    );

    expect($result)->toBeTrue();
});

test('implementsInterface returns true for class extending via interface', function () {
    $discovery = new class extends ClassDiscovery
    {
        public function testImplementsInterface(string $class, string $interface): bool
        {
            return $this->implementsInterface($class, $interface);
        }
    };

    // TestTool implements ToolContract (both the interface and extends the base)
    $result = $discovery->testImplementsInterface(
        \Atlasphp\Atlas\Tests\Fixtures\TestTool::class,
        ToolContract::class
    );

    expect($result)->toBeTrue();
});

test('implementsInterface handles class passed as interface parameter gracefully', function () {
    // When a class (not interface) is passed as the interface parameter,
    // PHP's implementsInterface() throws an exception, which is caught
    // and the method returns false for safety
    $discovery = new class extends ClassDiscovery
    {
        public function testImplementsInterface(string $class, string $interface): bool
        {
            return $this->implementsInterface($class, $interface);
        }
    };

    // Passing a class (AgentDefinition) instead of an interface triggers exception handling
    $result = $discovery->testImplementsInterface(
        \Atlasphp\Atlas\Tests\Fixtures\TestAgent::class,
        \Atlasphp\Atlas\Agents\AgentDefinition::class
    );

    // Method returns false because implementsInterface() throws for non-interface
    expect($result)->toBeFalse();
});

test('implementsInterface handles reflection exceptions gracefully', function () {
    $discovery = new class extends ClassDiscovery
    {
        public function testImplementsInterface(string $class, string $interface): bool
        {
            return $this->implementsInterface($class, $interface);
        }
    };

    // Pass something that will cause an error during reflection
    $result = $discovery->testImplementsInterface(
        'Invalid Class Name With Spaces',
        AgentContract::class
    );

    expect($result)->toBeFalse();
});

test('it ignores non-php files', function () {
    // Create a temp directory with mixed files
    $tempDir = sys_get_temp_dir().'/atlas-test-'.uniqid();
    mkdir($tempDir);

    try {
        // Create a non-PHP file
        file_put_contents($tempDir.'/readme.md', '# Test');
        file_put_contents($tempDir.'/config.json', '{}');
        file_put_contents($tempDir.'/script.js', 'console.log("test")');

        // Create a PHP file (won't match interface but tests the filtering)
        file_put_contents($tempDir.'/Test.php', '<?php class Test {}');

        $result = $this->discovery->discover($tempDir, 'TempNamespace', AgentContract::class);

        // Should not fail and should return empty (no matching classes)
        expect($result)->toBe([]);
    } finally {
        // Clean up
        @unlink($tempDir.'/readme.md');
        @unlink($tempDir.'/config.json');
        @unlink($tempDir.'/script.js');
        @unlink($tempDir.'/Test.php');
        @rmdir($tempDir);
    }
});
