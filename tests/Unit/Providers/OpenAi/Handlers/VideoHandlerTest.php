<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Video;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\VideoRequest;
use Atlasphp\Atlas\Responses\VideoResponse;
use Illuminate\Support\Facades\Http;

function makeOpenAiVideoHandler(): Video
{
    return new Video(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
        pollInterval: 0,
    );
}

function makeOpenAiVideoRequest(array $overrides = []): VideoRequest
{
    return new VideoRequest(
        model: $overrides['model'] ?? 'sora-2',
        instructions: $overrides['instructions'] ?? 'A cat playing piano',
        media: $overrides['media'] ?? [],
        duration: $overrides['duration'] ?? null,
        ratio: $overrides['ratio'] ?? null,
        format: $overrides['format'] ?? null,
        providerOptions: $overrides['providerOptions'] ?? [],
    );
}

it('posts to /v1/videos and polls until completed', function () {
    Http::fake([
        'api.openai.com/v1/videos' => Http::response(['id' => 'video_123', 'status' => 'queued']),
        'api.openai.com/v1/videos/video_123' => Http::response([
            'status' => 'completed',
            'model' => 'sora-2',
            'seconds' => '4',
            'size' => '720x1280',
        ]),
        'api.openai.com/v1/videos/video_123/content' => Http::response('fake-video-binary'),
    ]);

    $handler = makeOpenAiVideoHandler();
    $response = $handler->video(makeOpenAiVideoRequest());

    expect($response)->toBeInstanceOf(VideoResponse::class);
    expect($response->duration)->toBe(4);
    expect($response->meta['video_id'])->toBe('video_123');
    expect($response->format)->toBe('mp4');

    Http::assertSent(function ($request) {
        if ($request->url() === 'https://api.openai.com/v1/videos') {
            return $request['prompt'] === 'A cat playing piano'
                && $request['model'] === 'sora-2';
        }

        return true;
    });
});

it('maps duration to seconds string', function () {
    Http::fake([
        'api.openai.com/v1/videos' => Http::response(['id' => 'video_dur', 'status' => 'queued']),
        'api.openai.com/v1/videos/video_dur' => Http::response(['status' => 'completed', 'seconds' => '8']),
        'api.openai.com/v1/videos/video_dur/content' => Http::response('binary'),
    ]);

    $handler = makeOpenAiVideoHandler();
    $handler->video(makeOpenAiVideoRequest(['duration' => 8]));

    Http::assertSent(function ($request) {
        if ($request->url() === 'https://api.openai.com/v1/videos') {
            return $request['seconds'] === '8';
        }

        return true;
    });
});

it('maps ratio to size', function () {
    Http::fake([
        'api.openai.com/v1/videos' => Http::response(['id' => 'video_size', 'status' => 'queued']),
        'api.openai.com/v1/videos/video_size' => Http::response(['status' => 'completed']),
        'api.openai.com/v1/videos/video_size/content' => Http::response('binary'),
    ]);

    $handler = makeOpenAiVideoHandler();
    $handler->video(makeOpenAiVideoRequest(['ratio' => '16:9']));

    Http::assertSent(function ($request) {
        if ($request->url() === 'https://api.openai.com/v1/videos') {
            return $request['size'] === '1280x720';
        }

        return true;
    });
});

it('passes through WxH size directly', function () {
    Http::fake([
        'api.openai.com/v1/videos' => Http::response(['id' => 'video_wxh', 'status' => 'queued']),
        'api.openai.com/v1/videos/video_wxh' => Http::response(['status' => 'completed']),
        'api.openai.com/v1/videos/video_wxh/content' => Http::response('binary'),
    ]);

    $handler = makeOpenAiVideoHandler();
    $handler->video(makeOpenAiVideoRequest(['ratio' => '1920x1080']));

    Http::assertSent(function ($request) {
        if ($request->url() === 'https://api.openai.com/v1/videos') {
            return $request['size'] === '1920x1080';
        }

        return true;
    });
});

it('sends input_reference for image-to-video', function () {
    Http::fake([
        'api.openai.com/v1/videos' => Http::response(['id' => 'video_img', 'status' => 'queued']),
        'api.openai.com/v1/videos/video_img' => Http::response(['status' => 'completed']),
        'api.openai.com/v1/videos/video_img/content' => Http::response('binary'),
    ]);

    $image = Image::fromUrl('https://example.com/photo.jpg');

    $handler = makeOpenAiVideoHandler();
    $handler->video(makeOpenAiVideoRequest(['media' => [$image]]));

    Http::assertSent(function ($request) {
        if ($request->url() === 'https://api.openai.com/v1/videos') {
            return $request['input_reference']['image_url'] === 'https://example.com/photo.jpg';
        }

        return true;
    });
});

it('passes provider options through', function () {
    Http::fake([
        'api.openai.com/v1/videos' => Http::response(['id' => 'video_opts', 'status' => 'queued']),
        'api.openai.com/v1/videos/video_opts' => Http::response(['status' => 'completed']),
        'api.openai.com/v1/videos/video_opts/content' => Http::response('binary'),
    ]);

    $handler = makeOpenAiVideoHandler();
    $handler->video(makeOpenAiVideoRequest(['providerOptions' => ['custom_param' => 'value']]));

    Http::assertSent(function ($request) {
        if ($request->url() === 'https://api.openai.com/v1/videos') {
            return $request['custom_param'] === 'value';
        }

        return true;
    });
});

it('throws ProviderException when id is missing', function () {
    Http::fake([
        'api.openai.com/v1/videos' => Http::response(['status' => 'queued']),
    ]);

    $handler = makeOpenAiVideoHandler();

    $handler->video(makeOpenAiVideoRequest());
})->throws(ProviderException::class, 'missing id');

it('throws ProviderException when video generation fails', function () {
    Http::fake([
        'api.openai.com/v1/videos' => Http::response(['id' => 'video_fail', 'status' => 'queued']),
        'api.openai.com/v1/videos/video_fail' => Http::response([
            'status' => 'failed',
            'error' => ['message' => 'Content policy violation'],
        ]),
    ]);

    $handler = makeOpenAiVideoHandler();

    $handler->video(makeOpenAiVideoRequest());
})->throws(ProviderException::class, 'failed');

it('throws ProviderException on poll timeout', function () {
    Http::fake([
        'api.openai.com/v1/videos' => Http::response(['id' => 'video_timeout', 'status' => 'queued']),
        'api.openai.com/v1/videos/video_timeout' => Http::response(['status' => 'in_progress', 'progress' => 50]),
    ]);

    $handler = new Video(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
        pollInterval: 0,
        maxAttempts: 1,
    );

    $handler->video(makeOpenAiVideoRequest());
})->throws(ProviderException::class, 'timed out');

it('videoToText throws UnsupportedFeatureException', function () {
    $handler = makeOpenAiVideoHandler();

    $handler->videoToText(makeOpenAiVideoRequest());
})->throws(UnsupportedFeatureException::class);

it('downloads video binary from content endpoint', function () {
    $videoContent = random_bytes(100);

    Http::fake([
        'api.openai.com/v1/videos' => Http::response(['id' => 'video_dl', 'status' => 'queued']),
        'api.openai.com/v1/videos/video_dl' => Http::response(['status' => 'completed', 'seconds' => '4']),
        'api.openai.com/v1/videos/video_dl/content' => Http::response($videoContent),
    ]);

    $handler = makeOpenAiVideoHandler();
    $response = $handler->video(makeOpenAiVideoRequest());

    // The URL should be a temp file path
    expect(file_exists($response->url))->toBeTrue();
    expect(file_get_contents($response->url))->toBe($videoContent);

    // Clean up
    unlink($response->url);
});
