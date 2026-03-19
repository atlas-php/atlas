<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Xai\Handlers\Video;
use Atlasphp\Atlas\Requests\VideoRequest;
use Atlasphp\Atlas\Responses\VideoResponse;
use Illuminate\Support\Facades\Http;

function makeXaiVideoHandler(): Video
{
    return new Video(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.x.ai/v1']),
        http: app(HttpClient::class),
        pollInterval: 0,
    );
}

function makeXaiVideoRequest(array $overrides = []): VideoRequest
{
    return new VideoRequest(
        model: $overrides['model'] ?? 'grok-video',
        instructions: $overrides['instructions'] ?? 'A cat playing piano',
        media: $overrides['media'] ?? [],
        duration: $overrides['duration'] ?? null,
        ratio: $overrides['ratio'] ?? null,
        format: $overrides['format'] ?? null,
        providerOptions: $overrides['providerOptions'] ?? [],
    );
}

it('posts to /v1/videos/generations and polls until done', function () {
    Http::fake([
        'api.x.ai/v1/videos/generations' => Http::response(['request_id' => 'vid_123']),
        'api.x.ai/v1/videos/vid_123' => Http::response([
            'status' => 'done',
            'video' => [
                'url' => 'https://cdn.x.ai/videos/vid_123.mp4',
                'duration' => 5,
            ],
        ]),
    ]);

    $handler = makeXaiVideoHandler();
    $response = $handler->video(makeXaiVideoRequest());

    expect($response)->toBeInstanceOf(VideoResponse::class);
    expect($response->url)->toBe('https://cdn.x.ai/videos/vid_123.mp4');
    expect($response->duration)->toBe(5);
    expect($response->meta['request_id'])->toBe('vid_123');

    Http::assertSent(function ($request) {
        if ($request->url() === 'https://api.x.ai/v1/videos/generations') {
            return $request['prompt'] === 'A cat playing piano';
        }

        return true;
    });
});

it('sends duration and aspect_ratio', function () {
    Http::fake([
        'api.x.ai/v1/videos/generations' => Http::response(['request_id' => 'vid_456']),
        'api.x.ai/v1/videos/vid_456' => Http::response([
            'status' => 'done',
            'video' => ['url' => 'https://cdn.x.ai/videos/vid_456.mp4'],
        ]),
    ]);

    $handler = makeXaiVideoHandler();
    $handler->video(makeXaiVideoRequest([
        'duration' => 10,
        'ratio' => '16:9',
    ]));

    Http::assertSent(function ($request) {
        if ($request->url() === 'https://api.x.ai/v1/videos/generations') {
            return $request['duration'] === 10
                && $request['aspect_ratio'] === '16:9';
        }

        return true;
    });
});

it('throws ProviderException when video generation expires', function () {
    Http::fake([
        'api.x.ai/v1/videos/generations' => Http::response(['request_id' => 'vid_expired']),
        'api.x.ai/v1/videos/vid_expired' => Http::response([
            'status' => 'expired',
        ]),
    ]);

    $handler = makeXaiVideoHandler();

    $handler->video(makeXaiVideoRequest());
})->throws(ProviderException::class, 'expired');

it('throws ProviderException when video generation fails', function () {
    Http::fake([
        'api.x.ai/v1/videos/generations' => Http::response(['request_id' => 'vid_failed']),
        'api.x.ai/v1/videos/vid_failed' => Http::response([
            'status' => 'failed',
        ]),
    ]);

    $handler = makeXaiVideoHandler();

    $handler->video(makeXaiVideoRequest());
})->throws(ProviderException::class, 'failed');

it('throws ProviderException when request_id is missing', function () {
    Http::fake([
        'api.x.ai/v1/videos/generations' => Http::response(['status' => 'ok']),
    ]);

    $handler = makeXaiVideoHandler();

    $handler->video(makeXaiVideoRequest());
})->throws(ProviderException::class, 'missing request_id');

it('sends source image in body', function () {
    Http::fake([
        'api.x.ai/v1/videos/generations' => Http::response(['request_id' => 'vid_img']),
        'api.x.ai/v1/videos/vid_img' => Http::response([
            'status' => 'done',
            'video' => ['url' => 'https://cdn.x.ai/videos/vid_img.mp4'],
        ]),
    ]);

    $image = Image::fromUrl('https://example.com/photo.jpg');

    $handler = makeXaiVideoHandler();
    $handler->video(makeXaiVideoRequest(['media' => [$image]]));

    Http::assertSent(function ($request) {
        if ($request->url() === 'https://api.x.ai/v1/videos/generations') {
            return $request['image'] === 'https://example.com/photo.jpg';
        }

        return true;
    });
});

it('videoToText throws UnsupportedFeatureException', function () {
    $handler = makeXaiVideoHandler();

    $handler->videoToText(makeXaiVideoRequest());
})->throws(UnsupportedFeatureException::class);
