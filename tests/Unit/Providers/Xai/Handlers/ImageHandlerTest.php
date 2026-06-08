<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Input\Image as ImageInput;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Xai\Handlers\Image;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Responses\ImageResponse;
use Illuminate\Support\Facades\Http;

function makeXaiImageHandler(): Image
{
    return new Image(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.x.ai/v1']),
        http: app(HttpClient::class),
    );
}

function xaiImageRequest(array $media = []): ImageRequest
{
    return new ImageRequest(
        model: 'grok-imagine-image-quality',
        instructions: 'A blue square',
        media: $media,
        size: null,
        quality: null,
        format: null,
    );
}

it('sends text-to-image generation to /images/generations', function () {
    Http::fake([
        'api.x.ai/v1/images/generations' => Http::response([
            'data' => [['url' => 'https://imgen.x.ai/generated.png', 'revised_prompt' => 'A blue square']],
        ]),
    ]);

    $response = makeXaiImageHandler()->image(xaiImageRequest());

    expect($response)->toBeInstanceOf(ImageResponse::class)
        ->and($response->url)->toBe('https://imgen.x.ai/generated.png')
        ->and($response->revisedPrompt)->toBe('A blue square')
        ->and($response->base64)->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'https://api.x.ai/v1/images/generations'
        && $request['model'] === 'grok-imagine-image-quality'
        && $request['prompt'] === 'A blue square'
        && ! isset($request['image']));
});

it('routes image-to-image to /images/edits with a JSON image_url part', function () {
    Http::fake([
        'api.x.ai/v1/images/edits' => Http::response([
            'data' => [['url' => 'https://imgen.x.ai/edited.png']],
        ]),
    ]);

    $request = xaiImageRequest([ImageInput::fromBase64('cmF3Ynl0ZXM=', 'image/png')]);
    $response = makeXaiImageHandler()->image($request);

    expect($response->url)->toBe('https://imgen.x.ai/edited.png');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.x.ai/v1/images/edits'
            && $request['image']['type'] === 'image_url'
            && $request['image']['url'] === 'data:image/png;base64,cmF3Ynl0ZXM=';
    });
});

it('passes a hosted reference url through unchanged', function () {
    Http::fake([
        'api.x.ai/v1/images/edits' => Http::response(['data' => [['url' => 'https://imgen.x.ai/edited.png']]]),
    ]);

    $request = xaiImageRequest([ImageInput::fromUrl('https://example.com/ref.png', 'image/png')]);
    makeXaiImageHandler()->image($request);

    Http::assertSent(fn ($request) => $request['image']['url'] === 'https://example.com/ref.png');
});

it('sends multiple references as an array, capped at three', function () {
    Http::fake([
        'api.x.ai/v1/images/edits' => Http::response(['data' => [['url' => 'https://imgen.x.ai/edited.png']]]),
    ]);

    $request = xaiImageRequest([
        ImageInput::fromUrl('https://example.com/1.png', 'image/png'),
        ImageInput::fromUrl('https://example.com/2.png', 'image/png'),
        ImageInput::fromUrl('https://example.com/3.png', 'image/png'),
        ImageInput::fromUrl('https://example.com/4.png', 'image/png'),
    ]);
    makeXaiImageHandler()->image($request);

    Http::assertSent(function ($request) {
        $images = $request['image'];

        return is_array($images) && array_is_list($images) && count($images) === 3
            && $images[0]['url'] === 'https://example.com/1.png'
            && $images[2]['url'] === 'https://example.com/3.png';
    });
});

it('skips non-Input entries in the media array', function () {
    Http::fake([
        'api.x.ai/v1/images/edits' => Http::response(['data' => [['url' => 'https://imgen.x.ai/edited.png']]]),
    ]);

    $request = xaiImageRequest(['not-an-input', ImageInput::fromUrl('https://example.com/ref.png', 'image/png')]);
    makeXaiImageHandler()->image($request);

    Http::assertSent(function ($request) {
        // Only the real Input became a reference, so it is a single image_url object.
        return $request->url() === 'https://api.x.ai/v1/images/edits'
            && $request['image']['type'] === 'image_url'
            && $request['image']['url'] === 'https://example.com/ref.png';
    });
});

it('returns an empty reference when the response carries no image data', function () {
    Http::fake([
        'api.x.ai/v1/images/generations' => Http::response(['data' => []]),
    ]);

    $response = makeXaiImageHandler()->image(xaiImageRequest());

    expect($response->url)->toBe('')
        ->and($response->base64)->toBeNull()
        ->and($response->revisedPrompt)->toBeNull()
        ->and($response->format)->toBeNull();
});

it('surfaces a b64_json response as a data URI', function () {
    Http::fake([
        'api.x.ai/v1/images/generations' => Http::response(['data' => [['b64_json' => 'aGVsbG8=']]]),
    ]);

    $response = makeXaiImageHandler()->image(xaiImageRequest());

    expect($response->base64)->toBe('aGVsbG8=')
        ->and($response->url)->toBe('data:image/png;base64,aGVsbG8=')
        ->and($response->format)->toBe('png');
});

it('throws UnsupportedFeatureException for imageToText', function () {
    makeXaiImageHandler()->imageToText(xaiImageRequest());
})->throws(UnsupportedFeatureException::class);
