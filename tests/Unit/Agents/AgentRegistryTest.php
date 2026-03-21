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
