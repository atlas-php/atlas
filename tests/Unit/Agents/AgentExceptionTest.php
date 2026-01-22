<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Exceptions\AgentException;

test('it creates exception for execution failure', function () {
    $exception = AgentException::executionFailed('support-agent', 'API timeout');

    expect($exception)->toBeInstanceOf(AgentException::class);
    expect($exception->getMessage())->toBe("Agent 'support-agent' execution failed: API timeout");
});

test('it creates exception for execution failure with previous exception', function () {
    $previous = new RuntimeException('Connection refused');
    $exception = AgentException::executionFailed('support-agent', 'API timeout', $previous);

    expect($exception)->toBeInstanceOf(AgentException::class);
    expect($exception->getMessage())->toBe("Agent 'support-agent' execution failed: API timeout");
    expect($exception->getPrevious())->toBe($previous);
});

test('it creates exception for invalid configuration', function () {
    $exception = AgentException::invalidConfiguration('support-agent', 'provider is required');

    expect($exception)->toBeInstanceOf(AgentException::class);
    expect($exception->getMessage())->toBe("Agent 'support-agent' has invalid configuration: provider is required");
});

test('it creates exception for duplicate registration', function () {
    $exception = AgentException::duplicateRegistration('support-agent');

    expect($exception)->toBeInstanceOf(AgentException::class);
    expect($exception->getMessage())->toBe("An agent with key 'support-agent' has already been registered.");
});

test('it creates exception for resolution failure', function () {
    $exception = AgentException::resolutionFailed('App\\Agents\\UnknownAgent');

    expect($exception)->toBeInstanceOf(AgentException::class);
    expect($exception->getMessage())->toBe('Failed to resolve agent: App\\Agents\\UnknownAgent');
});
