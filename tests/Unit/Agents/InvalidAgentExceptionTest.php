<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Exceptions\InvalidAgentException;

test('it creates exception for missing required field', function () {
    $exception = InvalidAgentException::missingRequired('support-agent', 'provider');

    expect($exception)->toBeInstanceOf(InvalidAgentException::class);
    expect($exception->getMessage())->toBe("Agent 'support-agent' is missing required field: provider");
});

test('it creates exception for invalid provider', function () {
    $exception = InvalidAgentException::invalidProvider('support-agent', 'unknown-provider');

    expect($exception)->toBeInstanceOf(InvalidAgentException::class);
    expect($exception->getMessage())->toBe("Agent 'support-agent' has invalid provider: unknown-provider");
});

test('it creates exception for class not implementing contract', function () {
    $exception = InvalidAgentException::doesNotImplementContract('App\\InvalidAgent');

    expect($exception)->toBeInstanceOf(InvalidAgentException::class);
    expect($exception->getMessage())->toBe("Class 'App\\InvalidAgent' does not implement AgentContract.");
});
