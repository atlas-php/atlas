<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Exceptions\AgentNotFoundException;

test('it creates exception for agent not found by key', function () {
    $exception = AgentNotFoundException::forKey('support-agent');

    expect($exception)->toBeInstanceOf(AgentNotFoundException::class);
    expect($exception->getMessage())->toBe("No agent found with key 'support-agent'.");
});

test('it creates exception for agent not found by class', function () {
    $exception = AgentNotFoundException::forClass('App\\Agents\\NonExistentAgent');

    expect($exception)->toBeInstanceOf(AgentNotFoundException::class);
    expect($exception->getMessage())->toBe("Agent class 'App\\Agents\\NonExistentAgent' not found or could not be instantiated.");
});

test('forClass preserves full namespace path', function () {
    $exception = AgentNotFoundException::forClass('Vendor\\Package\\SubNamespace\\AgentClass');

    expect($exception->getMessage())->toContain('Vendor\\Package\\SubNamespace\\AgentClass');
});
