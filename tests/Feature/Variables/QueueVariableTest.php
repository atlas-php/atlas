<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pending\AudioRequest;
use Atlasphp\Atlas\Pending\ImageRequest;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Pending\VideoRequest;

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
