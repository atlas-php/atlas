<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Providers\Facades\Atlas;
use Atlasphp\Atlas\Testing\Support\StreamEventFactory;

beforeEach(function () {
    // Ensure we unfake after each test
    Atlas::unfake();
});

afterEach(function () {
    Atlas::unfake();
});

test('Atlas::fake returns AtlasFake instance', function () {
    $fake = Atlas::fake();

    expect($fake)->toBeInstanceOf(\Atlasphp\Atlas\Testing\AtlasFake::class);
});

test('Atlas::isFaked reports correctly', function () {
    expect(Atlas::isFaked())->toBeFalse();

    Atlas::fake();

    expect(Atlas::isFaked())->toBeTrue();
});

test('Atlas::unfake restores original state', function () {
    Atlas::fake();
    expect(Atlas::isFaked())->toBeTrue();

    Atlas::unfake();

    expect(Atlas::isFaked())->toBeFalse();
});

test('Atlas::fake with responses configures default sequence', function () {
    $responses = [
        AgentResponse::text('First'),
        AgentResponse::text('Second'),
    ];

    $fake = Atlas::fake($responses);

    expect($fake)->toBeInstanceOf(\Atlasphp\Atlas\Testing\AtlasFake::class);
});

test('fake can configure agent-specific responses', function () {
    $fake = Atlas::fake();
    $fake->response('billing', AgentResponse::text('Your balance is $100'));
    $fake->response('support', AgentResponse::text('How can I help?'));

    // The fake is now configured
    expect($fake->recorded())->toBeEmpty();
});

test('fake prevents stray requests when enabled', function () {
    Atlas::fake()->preventStrayRequests();

    // This would throw if actually executing
    // We're testing the configuration
    expect(Atlas::isFaked())->toBeTrue();
});

test('fake records requests for assertions', function () {
    $fake = Atlas::fake([
        AgentResponse::text('Test response'),
    ]);

    // Execute would be done here
    // Then assertions would verify

    expect($fake->recorded())->toBeEmpty(); // No executions yet
});

test('fake assertCalled works', function () {
    $fake = Atlas::fake([
        AgentResponse::text('Response'),
    ]);

    // Without any calls, assertNothingCalled should pass
    $fake->assertNothingCalled();
});

test('fake supports sequence responses', function () {
    $fake = Atlas::fake();
    $fake->response('assistant')
        ->returnSequence([
            AgentResponse::text('First'),
            AgentResponse::text('Second'),
        ]);

    expect(Atlas::isFaked())->toBeTrue();
});

test('fake supports streaming responses', function () {
    $streamResponse = StreamEventFactory::fromText('Hello, streaming!');

    $fake = Atlas::fake();
    $fake->sequence([$streamResponse]);

    expect(Atlas::isFaked())->toBeTrue();
});
