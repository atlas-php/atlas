<?php

declare(strict_types=1);

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Pending\AudioRequest;
use Atlasphp\Atlas\Pending\ImageRequest;
use Atlasphp\Atlas\Pending\MusicRequest;
use Atlasphp\Atlas\Pending\SfxRequest;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Pending\VideoRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Testing\AudioResponseFake;

it('text queue payload includes variables', function () {
    $request = app(TextRequest::class, [
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);

    $request->instructions('Hi {NAME}')
        ->withVariables(['NAME' => 'Tim'])
        ->withMessageInterpolation()
        ->message('hello');

    $payload = $request->toQueuePayload();

    expect($payload['variables'])->toBe(['NAME' => 'Tim']);
    expect($payload['interpolate_messages'])->toBeTrue();
});

it('image queue payload includes variables', function () {
    $request = app(ImageRequest::class, [
        'provider' => 'openai',
        'model' => 'dall-e-3',
    ]);

    $request->instructions('A {STYLE} logo')
        ->withVariables(['STYLE' => 'flat']);

    $payload = $request->toQueuePayload();

    expect($payload['variables'])->toBe(['STYLE' => 'flat']);
    expect($payload['interpolate_messages'])->toBeFalse();
});

it('audio queue payload includes variables', function () {
    $request = app(AudioRequest::class, [
        'provider' => 'openai',
        'model' => 'tts-1',
    ]);

    $request->instructions('Welcome {NAME}')
        ->withVariables(['NAME' => 'Tim']);

    $payload = $request->toQueuePayload();

    expect($payload['variables'])->toBe(['NAME' => 'Tim']);
});

it('video queue payload includes variables', function () {
    $request = app(VideoRequest::class, [
        'provider' => 'openai',
        'model' => 'sora',
    ]);

    $request->instructions('Demo for {PRODUCT}')
        ->withVariables(['PRODUCT' => 'Atlas']);

    $payload = $request->toQueuePayload();

    expect($payload['variables'])->toBe(['PRODUCT' => 'Atlas']);
});

it('music queue payload includes variables', function () {
    $request = app(MusicRequest::class, [
        'provider' => 'elevenlabs',
        'model' => 'v2',
    ]);

    $request->instructions('A {GENRE} track')
        ->withVariables(['GENRE' => 'ambient'])
        ->withMessageInterpolation();

    $payload = $request->toQueuePayload();

    expect($payload['variables'])->toBe(['GENRE' => 'ambient']);
    expect($payload['interpolate_messages'])->toBeTrue();
    expect($payload['instructions'])->toBe('A {GENRE} track');
});

it('sfx queue payload includes variables', function () {
    $request = app(SfxRequest::class, [
        'provider' => 'elevenlabs',
        'model' => 'v2',
    ]);

    $request->instructions('A {TYPE} explosion')
        ->withVariables(['TYPE' => 'dramatic']);

    $payload = $request->toQueuePayload();

    expect($payload['variables'])->toBe(['TYPE' => 'dramatic']);
    expect($payload['interpolate_messages'])->toBeFalse();
});

it('music executeFromPayload restores variables', function () {
    Atlas::fake([AudioResponseFake::make()]);

    $result = MusicRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'test',
            'instructions' => 'A {GENRE} track',
            'duration' => 30,
            'format' => 'mp3',
            'providerOptions' => [],
            'meta' => [],
            'variables' => ['GENRE' => 'ambient'],
            'interpolate_messages' => true,
        ],
        terminal: 'asAudio',
    );

    expect($result)->toBeInstanceOf(AudioResponse::class);
});

it('sfx executeFromPayload restores variables', function () {
    Atlas::fake([AudioResponseFake::make()]);

    $result = SfxRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'test',
            'instructions' => 'A {TYPE} explosion',
            'duration' => 5,
            'format' => 'mp3',
            'providerOptions' => [],
            'meta' => [],
            'variables' => ['TYPE' => 'dramatic'],
            'interpolate_messages' => false,
        ],
        terminal: 'asAudio',
    );

    expect($result)->toBeInstanceOf(AudioResponse::class);
});

it('text executeFromPayload restores variables', function () {
    $request = app(TextRequest::class, [
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);

    $request->instructions('Hi {NAME}')
        ->withVariables(['NAME' => 'Tim'])
        ->withMessageInterpolation()
        ->message('hello');

    $payload = $request->toQueuePayload();

    // Verify the payload can be used to reconstruct with variables
    expect($payload['variables'])->toBe(['NAME' => 'Tim']);
    expect($payload['interpolate_messages'])->toBeTrue();
    expect($payload['instructions'])->toBe('Hi {NAME}');
});
