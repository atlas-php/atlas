<?php

declare(strict_types=1);

use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\VideoResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// ─── ImageResponse ───────────────────────────────────────────────────────────

it('stores ImageResponse from URL', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
    Http::fake(['example.com/*' => Http::response('image-binary')]);

    $response = new ImageResponse(url: 'https://example.com/image.png');
    $path = $response->storeAs('generated/image.png');

    expect($path)->toBe('generated/image.png');
    Storage::disk('local')->assertExists('generated/image.png');
    expect(Storage::disk('local')->get('generated/image.png'))->toBe('image-binary');
});

it('stores ImageResponse from base64 (prefers over URL)', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $response = new ImageResponse(
        url: 'https://example.com/image.png',
        base64: base64_encode('base64-image-data'),
    );

    $path = $response->storeAs('generated/image.png');

    expect(Storage::disk('local')->get('generated/image.png'))->toBe('base64-image-data');
    Http::assertNothingSent();
});

it('ImageResponse uses format for extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $response = new ImageResponse(
        url: 'https://example.com/image.webp',
        base64: base64_encode('x'),
        format: 'webp',
    );

    $path = $response->store();

    expect($path)->toEndWith('.webp');
});

it('ImageResponse defaults to png extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $response = new ImageResponse(url: 'x', base64: base64_encode('x'));
    $path = $response->store();

    expect($path)->toEndWith('.png');
});

it('ImageResponse contents from base64', function () {
    $response = new ImageResponse(url: 'x', base64: base64_encode('hello'));

    expect($response->contents())->toBe('hello');
});

it('ImageResponse toBase64', function () {
    $response = new ImageResponse(url: 'x', base64: base64_encode('hello'));

    expect($response->toBase64())->toBe(base64_encode('hello'));
});

// ─── AudioResponse ───────────────────────────────────────────────────────────

it('stores AudioResponse from base64 data', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $response = new AudioResponse(data: base64_encode('audio-binary'), format: 'mp3');
    $path = $response->storeAs('audio/speech.mp3');

    expect($path)->toBe('audio/speech.mp3');
    Storage::disk('local')->assertExists('audio/speech.mp3');
    expect(Storage::disk('local')->get('audio/speech.mp3'))->toBe('audio-binary');
});

it('AudioResponse defaults to mp3 extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $response = new AudioResponse(data: base64_encode('x'));
    $path = $response->store();

    expect($path)->toEndWith('.mp3');
});

it('AudioResponse __toString returns raw binary', function () {
    $response = new AudioResponse(data: base64_encode('audio-bytes'));

    expect((string) $response)->toBe('audio-bytes');
});

// ─── VideoResponse ───────────────────────────────────────────────────────────

it('stores VideoResponse from URL', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
    Http::fake(['example.com/*' => Http::response('video-binary')]);

    $response = new VideoResponse(url: 'https://example.com/video.mp4');
    $path = $response->storeAs('videos/clip.mp4');

    expect($path)->toBe('videos/clip.mp4');
    Storage::disk('local')->assertExists('videos/clip.mp4');
});

it('VideoResponse uses format for extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
    Http::fake(['*' => Http::response('x')]);

    $response = new VideoResponse(url: 'https://example.com/video.webm', format: 'webm');
    $path = $response->store();

    expect($path)->toEndWith('.webm');
});

it('VideoResponse defaults to mp4 extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
    Http::fake(['*' => Http::response('x')]);

    $response = new VideoResponse(url: 'https://example.com/video');
    $path = $response->store();

    expect($path)->toEndWith('.mp4');
});

// ─── storePublicly / storePubliclyAs on responses ────────────────────────────

it('storePublicly works on responses', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $response = new AudioResponse(data: base64_encode('data'));
    $path = $response->storePublicly();

    Storage::disk('local')->assertExists($path);
});

it('storePubliclyAs works on responses', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $response = new AudioResponse(data: base64_encode('data'));
    $path = $response->storePubliclyAs('public/audio.mp3');

    expect($path)->toBe('public/audio.mp3');
    Storage::disk('local')->assertExists('public/audio.mp3');
});

it('store to specific disk on responses', function () {
    Storage::fake('s3');

    $response = new AudioResponse(data: base64_encode('data'));
    $path = $response->store('s3');

    Storage::disk('s3')->assertExists($path);
});
