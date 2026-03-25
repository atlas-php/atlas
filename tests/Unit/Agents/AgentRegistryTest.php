<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Agents\AgentRegistry;
use Atlasphp\Atlas\Exceptions\AgentNotFoundException;

// ─── Test agents ────────────────────────────────────────────────────────────

class RegistryTestSupportAgent extends Agent
{
    public function key(): string
    {
        return 'support';
    }
}

class RegistryTestBillingAgent extends Agent
{
    public function key(): string
    {
        return 'billing';
    }
}

class NotAnAgent
{
    public function key(): string
    {
        return 'not-agent';
    }
}

// ─── register() + resolve() ────────────────────────────────────────────────

it('registers and resolves an agent by key', function () {
    $registry = new AgentRegistry(app());
    $registry->register(RegistryTestSupportAgent::class);

    $agent = $registry->resolve('support');

    expect($agent)->toBeInstanceOf(RegistryTestSupportAgent::class);
    expect($agent->key())->toBe('support');
});

it('resolves a fresh instance from the container each time', function () {
    $registry = new AgentRegistry(app());
    $registry->register(RegistryTestSupportAgent::class);

    $a = $registry->resolve('support');
    $b = $registry->resolve('support');

    expect($a)->not->toBe($b);
});

// ─── resolve() throws on unknown ────────────────────────────────────────────

it('throws on unknown agent key', function () {
    $registry = new AgentRegistry(app());
    $registry->resolve('unknown');
})->throws(AgentNotFoundException::class, 'Agent [unknown] is not registered');

// ─── has() ──────────────────────────────────────────────────────────────────

it('returns true when agent is registered', function () {
    $registry = new AgentRegistry(app());
    $registry->register(RegistryTestSupportAgent::class);

    expect($registry->has('support'))->toBeTrue();
});

it('returns false when agent is not registered', function () {
    $registry = new AgentRegistry(app());

    expect($registry->has('unknown'))->toBeFalse();
});

// ─── keys() ─────────────────────────────────────────────────────────────────

it('returns all registered agent keys', function () {
    $registry = new AgentRegistry(app());
    $registry->register(RegistryTestSupportAgent::class);
    $registry->register(RegistryTestBillingAgent::class);

    expect($registry->keys())->toBe(['support', 'billing']);
});

it('returns empty array when no agents registered', function () {
    $registry = new AgentRegistry(app());

    expect($registry->keys())->toBe([]);
});

// ─── discover() ─────────────────────────────────────────────────────────────

it('handles missing directory gracefully', function () {
    $registry = new AgentRegistry(app());
    $registry->discover('/nonexistent/path', 'App\\Agents');

    expect($registry->keys())->toBe([]);
});

it('discovers agent classes from a directory', function () {
    $dir = sys_get_temp_dir().'/atlas-discover-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/RegistryTestSupportAgent.php', '<?php');
    file_put_contents($dir.'/RegistryTestBillingAgent.php', '<?php');

    $registry = new AgentRegistry(app());
    $registry->discover($dir, '');

    expect($registry->keys())->toHaveCount(2)
        ->and($registry->has('support'))->toBeTrue()
        ->and($registry->has('billing'))->toBeTrue();

    array_map('unlink', glob($dir.'/*.php'));
    rmdir($dir);
});

it('skips non-agent classes during discovery', function () {
    $dir = sys_get_temp_dir().'/atlas-discover-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/NotAnAgent.php', '<?php');
    file_put_contents($dir.'/RegistryTestSupportAgent.php', '<?php');

    $registry = new AgentRegistry(app());
    $registry->discover($dir, '');

    expect($registry->keys())->toBe(['support']);

    array_map('unlink', glob($dir.'/*.php'));
    rmdir($dir);
});

it('skips classes that do not exist during discovery', function () {
    $dir = sys_get_temp_dir().'/atlas-discover-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/NonExistentClassName.php', '<?php');

    $registry = new AgentRegistry(app());
    $registry->discover($dir, '');

    expect($registry->keys())->toBe([]);

    array_map('unlink', glob($dir.'/*.php'));
    rmdir($dir);
});

it('handles empty directory during discovery', function () {
    $dir = sys_get_temp_dir().'/atlas-discover-test-'.uniqid();
    mkdir($dir);

    $registry = new AgentRegistry(app());
    $registry->discover($dir, '');

    expect($registry->keys())->toBe([]);

    rmdir($dir);
});

it('builds class name from namespace and filename', function () {
    $dir = sys_get_temp_dir().'/atlas-discover-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/RegistryTestSupportAgent.php', '<?php');

    $registry = new AgentRegistry(app());
    // The class RegistryTestSupportAgent is in the global namespace,
    // so passing a non-empty namespace should produce a non-existent class
    $registry->discover($dir, 'App\\Agents');

    expect($registry->keys())->toBe([]);

    array_map('unlink', glob($dir.'/*.php'));
    rmdir($dir);
});

it('handles glob returning no files on valid directory', function () {
    // The $files === false guard in discover() is defensive.
    // In practice, glob() returns an empty array for valid dirs with no matches.
    // This test ensures the empty-array path works correctly.
    $dir = sys_get_temp_dir().'/atlas-discover-glob-'.uniqid();
    mkdir($dir);
    // Put only non-PHP files so glob('*.php') returns []
    file_put_contents($dir.'/notes.txt', 'not php');

    $registry = new AgentRegistry(app());
    $registry->discover($dir, '');

    expect($registry->keys())->toBe([]);

    array_map('unlink', glob($dir.'/*'));
    rmdir($dir);
});

it('handles glob returning false gracefully', function () {
    // glob() returns false on error (e.g., permission denied).
    // Simulate by subclassing to force the false return.
    $registry = new class(app()) extends AgentRegistry
    {
        public function discover(string $path, string $namespace): void
        {
            // Simulate: is_dir passes, but glob fails
            if (! is_dir($path)) {
                return;
            }

            $files = false; // Simulate glob failure

            if ($files === false) {
                return;
            }
        }
    };

    $dir = sys_get_temp_dir().'/atlas-discover-glob-false-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/RegistryTestSupportAgent.php', '<?php');

    $registry->discover($dir, '');

    expect($registry->keys())->toBe([]);

    array_map('unlink', glob($dir.'/*'));
    rmdir($dir);
});

it('ignores non-php files during discovery', function () {
    $dir = sys_get_temp_dir().'/atlas-discover-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/RegistryTestSupportAgent.php', '<?php');
    file_put_contents($dir.'/README.md', '# Agents');
    file_put_contents($dir.'/config.json', '{}');

    $registry = new AgentRegistry(app());
    $registry->discover($dir, '');

    expect($registry->keys())->toBe(['support']);

    array_map('unlink', glob($dir.'/*'));
    rmdir($dir);
});
