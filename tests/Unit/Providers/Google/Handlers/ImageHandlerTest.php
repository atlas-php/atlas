<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\Google\Handlers\Image;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Responses\ImageResponse;
use Illuminate\Support\Facades\Http;

function makeGoogleImageHandler(): Image
{
    return new Image(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://generativelanguage.googleapis.com']),
        http: app(HttpClient::class),
    );
}

it('sends generateContent with responseModalities', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [
                    ['inline_data' => ['mime_type' => 'image/png', 'data' => 'abc123base64']],
                    ['text' => 'A generated image'],
                ], 'role' => 'model'], 'finishReason' => 'STOP'],
            ],
        ]),
    ]);

    $request = new ImageRequest(
        model: 'gemini-2.0-flash-exp',
        instructions: 'Draw a cat',
        media: [],
        size: null,
        quality: null,
        format: null,
    );

    $handler = makeGoogleImageHandler();
    $response = $handler->image($request);

    expect($response)->toBeInstanceOf(ImageResponse::class);
    expect($response->base64)->toBe('abc123base64');
    expect($response->revisedPrompt)->toBe('A generated image');
    expect($response->url)->toBe('data:image/png;base64,abc123base64');

    Http::assertSent(function ($request) {
        return $request['generationConfig']['responseModalities'] === ['IMAGE', 'TEXT']
            && str_contains($request->url(), ':generateContent');
    });
});

it('extracts inline_data as base64 from response parts', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [
                    ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => 'jpegdata']],
                ], 'role' => 'model'], 'finishReason' => 'STOP'],
            ],
        ]),
    ]);

    $request = new ImageRequest(
        model: 'gemini-2.0-flash-exp',
        instructions: 'Draw something',
        media: [],
        size: null,
        quality: null,
        format: null,
    );

    $handler = makeGoogleImageHandler();
    $response = $handler->image($request);

    expect($response->base64)->toBe('jpegdata');
    expect($response->url)->toBe('data:image/jpeg;base64,jpegdata');
});

it('throws UnsupportedFeatureException for imageToText', function () {
    $handler = makeGoogleImageHandler();

    $request = new ImageRequest(
        model: 'gemini-2.0-flash-exp',
        instructions: 'Describe',
        media: [],
        size: null,
        quality: null,
        format: null,
    );

    $handler->imageToText($request);
})->throws(UnsupportedFeatureException::class);
