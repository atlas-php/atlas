<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\PendingFakeRequest;
use Atlasphp\Atlas\Testing\Support\StreamEventFactory;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->container = new Container;
    $this->fake = new AtlasFake($this->container);
});

test('return with AgentResponse registers sequence and returns AtlasFake', function () {
    $pending = new PendingFakeRequest($this->fake, 'test-agent');

    $result = $pending->return(AgentResponse::text('Hello'));

    expect($result)->toBe($this->fake);
});

test('return with StreamResponse registers sequence', function () {
    $pending = new PendingFakeRequest($this->fake, 'test-agent');
    $streamResponse = StreamEventFactory::fromText('Streamed');

    $result = $pending->return($streamResponse);

    expect($result)->toBe($this->fake);
});

test('returnSequence registers multiple responses', function () {
    $pending = new PendingFakeRequest($this->fake, 'test-agent');

    $result = $pending->returnSequence([
        AgentResponse::text('First'),
        AgentResponse::text('Second'),
        AgentResponse::text('Third'),
    ]);

    expect($result)->toBe($this->fake);
});

test('throw registers exception and returns AtlasFake', function () {
    $pending = new PendingFakeRequest($this->fake, 'test-agent');
    $exception = new RuntimeException('Test error');

    $result = $pending->throw($exception);

    expect($result)->toBe($this->fake);
});

test('whenEmpty sets fallback response and returns self', function () {
    $pending = new PendingFakeRequest($this->fake, 'test-agent');

    $result = $pending->whenEmpty(AgentResponse::text('Fallback'));

    expect($result)->toBe($pending);
});

test('whenEmpty with exception sets fallback', function () {
    $pending = new PendingFakeRequest($this->fake, 'test-agent');
    $exception = new RuntimeException('Exhausted');

    $result = $pending->whenEmpty($exception);

    expect($result)->toBe($pending);
});

test('chained whenEmpty and return works', function () {
    $pending = new PendingFakeRequest($this->fake, 'test-agent');

    $result = $pending
        ->whenEmpty(AgentResponse::text('Fallback'))
        ->return(AgentResponse::text('First'));

    expect($result)->toBe($this->fake);
});

test('chained whenEmpty and returnSequence works', function () {
    $pending = new PendingFakeRequest($this->fake, 'test-agent');

    $result = $pending
        ->whenEmpty(AgentResponse::text('Fallback'))
        ->returnSequence([
            AgentResponse::text('First'),
            AgentResponse::text('Second'),
        ]);

    expect($result)->toBe($this->fake);
});
