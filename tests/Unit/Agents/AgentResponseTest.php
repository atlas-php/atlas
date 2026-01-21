<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Support\AgentResponse;

test('it creates with default values', function () {
    $response = new AgentResponse;

    expect($response->text)->toBeNull();
    expect($response->structured)->toBeNull();
    expect($response->toolCalls)->toBe([]);
    expect($response->usage)->toBe([]);
    expect($response->metadata)->toBe([]);
});

test('it creates text response', function () {
    $response = AgentResponse::text('Hello world');

    expect($response->text)->toBe('Hello world');
    expect($response->hasText())->toBeTrue();
});

test('it creates structured response', function () {
    $data = ['name' => 'John', 'age' => 30];
    $response = AgentResponse::structured($data);

    expect($response->structured)->toBe($data);
    expect($response->hasStructured())->toBeTrue();
});

test('it creates response with tool calls', function () {
    $toolCalls = [
        ['name' => 'search', 'arguments' => ['query' => 'test']],
    ];
    $response = AgentResponse::withToolCalls($toolCalls);

    expect($response->toolCalls)->toBe($toolCalls);
    expect($response->hasToolCalls())->toBeTrue();
});

test('it creates empty response', function () {
    $response = AgentResponse::empty();

    expect($response->hasText())->toBeFalse();
    expect($response->hasStructured())->toBeFalse();
    expect($response->hasToolCalls())->toBeFalse();
});

test('it reports hasText correctly', function () {
    expect((new AgentResponse)->hasText())->toBeFalse();
    expect((new AgentResponse(text: ''))->hasText())->toBeFalse();
    expect((new AgentResponse(text: 'hello'))->hasText())->toBeTrue();
});

test('it reports hasUsage correctly', function () {
    $without = new AgentResponse;
    $with = new AgentResponse(usage: ['total_tokens' => 100]);

    expect($without->hasUsage())->toBeFalse();
    expect($with->hasUsage())->toBeTrue();
});

test('it returns total tokens', function () {
    $response = new AgentResponse(usage: ['total_tokens' => 150]);

    expect($response->totalTokens())->toBe(150);
});

test('it returns prompt tokens', function () {
    $response = new AgentResponse(usage: ['prompt_tokens' => 50]);

    expect($response->promptTokens())->toBe(50);
});

test('it returns completion tokens', function () {
    $response = new AgentResponse(usage: ['completion_tokens' => 100]);

    expect($response->completionTokens())->toBe(100);
});

test('it returns zero for missing usage values', function () {
    $response = new AgentResponse;

    expect($response->totalTokens())->toBe(0);
    expect($response->promptTokens())->toBe(0);
    expect($response->completionTokens())->toBe(0);
});

test('it gets metadata value with default', function () {
    $response = new AgentResponse(metadata: ['key' => 'value']);

    expect($response->get('key'))->toBe('value');
    expect($response->get('missing', 'default'))->toBe('default');
});

test('it creates new instance with merged metadata', function () {
    $response = new AgentResponse(metadata: ['a' => 1]);

    $newResponse = $response->withMetadata(['b' => 2]);

    expect($newResponse)->not->toBe($response);
    expect($newResponse->metadata)->toBe(['a' => 1, 'b' => 2]);
});

test('it creates new instance with usage', function () {
    $response = new AgentResponse;
    $usage = ['total_tokens' => 100];

    $newResponse = $response->withUsage($usage);

    expect($newResponse)->not->toBe($response);
    expect($newResponse->usage)->toBe($usage);
});
