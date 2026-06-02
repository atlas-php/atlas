<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Http\HttpClient;
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

it('returns multiple URLs when count > 1', function () {
    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response([
            'data' => [
                ['url' => 'https://images.openai.com/img1.png', 'revised_prompt' => 'A cat v1'],
                ['url' => 'https://images.openai.com/img2.png', 'revised_prompt' => 'A cat v2'],
                ['url' => 'https://images.openai.com/img3.png', 'revised_prompt' => 'A cat v3'],
            ],
        ]),
    ]);

    $request = new ImageRequest(
        model: 'dall-e-2',
        instructions: 'A cat',
        media: [],
        size: '512x512',
        quality: null,
        format: null,
        count: 3,
    );

    $handler = makeImageHandler();
    $response = $handler->image($request);

    expect($response->url)->toBeArray();
    expect($response->url)->toHaveCount(3);
    expect($response->url[0])->toBe('https://images.openai.com/img1.png');
    expect($response->url[1])->toBe('https://images.openai.com/img2.png');
    expect($response->url[2])->toBe('https://images.openai.com/img3.png');
    expect($response->meta['count'])->toBe(3);

    Http::assertSent(function ($request) {
        return $request['n'] === 3;
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

it('populates base64 and a data URI when the response is b64_json (gpt-image-1)', function () {
    $raw = 'fake-png-bytes';
    $b64 = base64_encode($raw);

    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response([
            'data' => [
                ['b64_json' => $b64],
            ],
        ]),
    ]);

    $request = new ImageRequest(
        model: 'gpt-image-1',
        instructions: 'A blue square',
        media: [],
        size: '1024x1024',
        quality: null,
        format: null,
    );

    $response = makeImageHandler()->image($request);

    expect($response->base64)->toBe($b64);
    expect($response->format)->toBe('png');
    expect($response->url)->toBe("data:image/png;base64,{$b64}");
    // Storage path: contents() must decode the base64, not try to fetch it as a URL.
    expect($response->contents())->toBe($raw);
    expect($response->revisedPrompt)->toBeNull();
});

it('honours output_format provider option for b64_json responses', function () {
    $b64 = base64_encode('jpeg-bytes');

    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response([
            'data' => [
                ['b64_json' => $b64],
            ],
        ]),
    ]);

    $request = new ImageRequest(
        model: 'gpt-image-1',
        instructions: 'A blue square',
        media: [],
        size: '1024x1024',
        quality: null,
        format: null,
        providerOptions: ['output_format' => 'jpeg'],
    );

    $response = makeImageHandler()->image($request);

    expect($response->format)->toBe('jpeg');
    expect($response->url)->toBe("data:image/jpeg;base64,{$b64}");
});

it('uses request format over the png default for b64_json responses', function () {
    $b64 = base64_encode('webp-bytes');

    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response([
            'data' => [
                ['b64_json' => $b64],
            ],
        ]),
    ]);

    $request = new ImageRequest(
        model: 'gpt-image-1',
        instructions: 'A blue square',
        media: [],
        size: '1024x1024',
        quality: null,
        format: 'webp',
    );

    $response = makeImageHandler()->image($request);

    expect($response->format)->toBe('webp');
    expect($response->url)->toBe("data:image/webp;base64,{$b64}");
});

it('prefers a hosted URL and leaves base64 null when both url and b64_json are present', function () {
    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response([
            'data' => [
                ['url' => 'https://images.openai.com/generated.png', 'b64_json' => base64_encode('ignored')],
            ],
        ]),
    ]);

    $request = new ImageRequest(
        model: 'gpt-image-1',
        instructions: 'A blue square',
        media: [],
        size: '1024x1024',
        quality: null,
        format: null,
    );

    $response = makeImageHandler()->image($request);

    expect($response->url)->toBe('https://images.openai.com/generated.png');
    expect($response->base64)->toBeNull();
    expect($response->format)->toBeNull();
});

it('returns data URIs and first base64 when count > 1 with b64_json', function () {
    $a = base64_encode('img-a');
    $b = base64_encode('img-b');

    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response([
            'data' => [
                ['b64_json' => $a],
                ['b64_json' => $b],
            ],
        ]),
    ]);

    $request = new ImageRequest(
        model: 'gpt-image-1',
        instructions: 'Two squares',
        media: [],
        size: '1024x1024',
        quality: null,
        format: null,
        count: 2,
    );

    $response = makeImageHandler()->image($request);

    expect($response->url)->toBe([
        "data:image/png;base64,{$a}",
        "data:image/png;base64,{$b}",
    ]);
    expect($response->base64)->toBe($a);
    expect($response->format)->toBe('png');
    expect($response->meta['count'])->toBe(2);
});

it('leaves base64 and format null for hosted URL responses', function () {
    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response([
            'data' => [
                ['url' => 'https://images.openai.com/generated.png', 'revised_prompt' => 'A cat'],
            ],
        ]),
    ]);

    $request = new ImageRequest(
        model: 'dall-e-3',
        instructions: 'A cat',
        media: [],
        size: '1024x1024',
        quality: null,
        format: null,
    );

    $response = makeImageHandler()->image($request);

    expect($response->url)->toBe('https://images.openai.com/generated.png');
    expect($response->base64)->toBeNull();
    expect($response->format)->toBeNull();
});
