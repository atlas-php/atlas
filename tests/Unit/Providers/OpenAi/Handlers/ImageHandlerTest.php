<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Image;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Responses\ImageResponse;
use Illuminate\Support\Facades\Http;

function makeImageHandler(): Image
{
    return new Image(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );
}

it('sends image generation request to /v1/images/generations', function () {
    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response([
            'data' => [
                ['url' => 'https://images.openai.com/generated.png', 'revised_prompt' => 'A cute cat sitting'],
            ],
        ]),
    ]);

    $request = new ImageRequest(
        model: 'dall-e-3',
        instructions: 'A cat',
        media: [],
        size: '1024x1024',
        quality: 'hd',
        format: null,
    );

    $handler = makeImageHandler();
    $response = $handler->image($request);

    expect($response)->toBeInstanceOf(ImageResponse::class);
    expect($response->url)->toBe('https://images.openai.com/generated.png');
    expect($response->revisedPrompt)->toBe('A cute cat sitting');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/images/generations'
            && $request['model'] === 'dall-e-3'
            && $request['prompt'] === 'A cat'
            && $request['size'] === '1024x1024';
    });
});

it('throws UnsupportedFeatureException for imageToText', function () {
    $handler = makeImageHandler();

    $request = new ImageRequest(
        model: 'dall-e-3',
        instructions: 'Describe',
        media: [],
        size: null,
        quality: null,
        format: null,
    );

    $handler->imageToText($request);
})->throws(UnsupportedFeatureException::class);
