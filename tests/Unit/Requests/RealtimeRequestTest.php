<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\RealtimeTransport;
use Atlasphp\Atlas\Enums\TurnDetectionMode;
use Atlasphp\Atlas\Requests\RealtimeRequest;

it('constructs with all parameters', function () {
    $request = new RealtimeRequest(
        model: 'gpt-4o-realtime-preview',
        instructions: 'Be helpful',
        voice: 'alloy',
        transport: RealtimeTransport::WebRtc,
        turnDetection: TurnDetectionMode::ServerVad,
        vadThreshold: 0.5,
        vadSilenceDuration: 500,
        inputAudioFormat: 'pcm16',
        outputAudioFormat: 'pcm16',
        temperature: 0.8,
        maxResponseTokens: 4096,
        tools: [['type' => 'function', 'name' => 'test']],
        providerOptions: ['key' => 'val'],
        middleware: [],
        meta: ['test' => true],
    );

    expect($request->model)->toBe('gpt-4o-realtime-preview');
    expect($request->instructions)->toBe('Be helpful');
    expect($request->voice)->toBe('alloy');
    expect($request->transport)->toBe(RealtimeTransport::WebRtc);
    expect($request->turnDetection)->toBe(TurnDetectionMode::ServerVad);
    expect($request->vadThreshold)->toBe(0.5);
    expect($request->vadSilenceDuration)->toBe(500);
    expect($request->inputAudioFormat)->toBe('pcm16');
    expect($request->outputAudioFormat)->toBe('pcm16');
    expect($request->temperature)->toBe(0.8);
    expect($request->maxResponseTokens)->toBe(4096);
    expect($request->tools)->toHaveCount(1);
    expect($request->providerOptions)->toBe(['key' => 'val']);
    expect($request->meta)->toBe(['test' => true]);
});

it('has sensible defaults', function () {
    $request = new RealtimeRequest(
        model: 'gpt-4o-realtime-preview',
        instructions: null,
        voice: null,
    );

    expect($request->transport)->toBe(RealtimeTransport::WebRtc);
    expect($request->turnDetection)->toBe(TurnDetectionMode::ServerVad);
    expect($request->vadThreshold)->toBeNull();
    expect($request->vadSilenceDuration)->toBeNull();
    expect($request->inputAudioFormat)->toBeNull();
    expect($request->outputAudioFormat)->toBeNull();
    expect($request->temperature)->toBeNull();
    expect($request->maxResponseTokens)->toBeNull();
    expect($request->inputAudioTranscription)->toBeNull();
    expect($request->tools)->toBe([]);
    expect($request->providerOptions)->toBe([]);
    expect($request->middleware)->toBe([]);
    expect($request->meta)->toBe([]);
});

it('is immutable via readonly properties', function () {
    $request = new RealtimeRequest(
        model: 'model',
        instructions: 'test',
        voice: 'alloy',
    );

    $reflection = new ReflectionClass($request);

    foreach ($reflection->getProperties() as $prop) {
        expect($prop->isReadOnly())->toBeTrue("Property {$prop->getName()} should be readonly");
    }
});
