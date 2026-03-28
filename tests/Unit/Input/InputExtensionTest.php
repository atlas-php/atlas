<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Audio;
use Atlasphp\Atlas\Input\Document;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Input\Video;
use Illuminate\Support\Facades\Storage;

// ─── Image extensions ───────────────────────────────────────────────────────

it('Image uses gif extension for image/gif', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Image::fromBase64(base64_encode('x'), 'image/gif')->store();

    expect($path)->toEndWith('.gif');
});

it('Image uses webp extension for image/webp', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Image::fromBase64(base64_encode('x'), 'image/webp')->store();

    expect($path)->toEndWith('.webp');
});

it('Image uses svg extension for image/svg+xml', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Image::fromBase64(base64_encode('x'), 'image/svg+xml')->store();

    expect($path)->toEndWith('.svg');
});

it('Image defaults to jpg for unknown mime', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Image::fromBase64(base64_encode('x'), 'image/tiff')->store();

    expect($path)->toEndWith('.jpg');
});

// ─── Audio extensions ───────────────────────────────────────────────────────

it('Audio uses flac extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Audio::fromBase64(base64_encode('x'), 'audio/flac')->store();

    expect($path)->toEndWith('.flac');
});

it('Audio uses ogg extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Audio::fromBase64(base64_encode('x'), 'audio/ogg')->store();

    expect($path)->toEndWith('.ogg');
});

it('Audio uses webm extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Audio::fromBase64(base64_encode('x'), 'audio/webm')->store();

    expect($path)->toEndWith('.webm');
});

it('Audio uses m4a extension for audio/mp4', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Audio::fromBase64(base64_encode('x'), 'audio/mp4')->store();

    expect($path)->toEndWith('.m4a');
});

it('Audio uses m4a extension for audio/m4a', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Audio::fromBase64(base64_encode('x'), 'audio/m4a')->store();

    expect($path)->toEndWith('.m4a');
});

it('Audio uses wav extension for audio/x-wav', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Audio::fromBase64(base64_encode('x'), 'audio/x-wav')->store();

    expect($path)->toEndWith('.wav');
});

it('Audio defaults to mp3 for unknown mime', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Audio::fromBase64(base64_encode('x'), 'audio/aac')->store();

    expect($path)->toEndWith('.mp3');
});

// ─── Video extensions ───────────────────────────────────────────────────────

it('Video uses webm extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Video::fromBase64(base64_encode('x'), 'video/webm')->store();

    expect($path)->toEndWith('.webm');
});

it('Video uses mov extension for video/quicktime', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Video::fromBase64(base64_encode('x'), 'video/quicktime')->store();

    expect($path)->toEndWith('.mov');
});

it('Video defaults to mp4 for unknown mime', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Video::fromBase64(base64_encode('x'), 'video/avi')->store();

    expect($path)->toEndWith('.mp4');
});

// ─── Document extensions ────────────────────────────────────────────────────

it('Document uses txt extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Document::fromBase64(base64_encode('x'), 'text/plain')->store();

    expect($path)->toEndWith('.txt');
});

it('Document uses md extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Document::fromBase64(base64_encode('x'), 'text/markdown')->store();

    expect($path)->toEndWith('.md');
});

it('Document uses html extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Document::fromBase64(base64_encode('x'), 'text/html')->store();

    expect($path)->toEndWith('.html');
});

it('Document uses csv extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Document::fromBase64(base64_encode('x'), 'text/csv')->store();

    expect($path)->toEndWith('.csv');
});

it('Document uses json extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Document::fromBase64(base64_encode('x'), 'application/json')->store();

    expect($path)->toEndWith('.json');
});

it('Document uses docx extension', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Document::fromBase64(base64_encode('x'), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')->store();

    expect($path)->toEndWith('.docx');
});

it('Document defaults to pdf for unknown mime', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $path = Document::fromBase64(base64_encode('x'), 'application/octet-stream')->store();

    expect($path)->toEndWith('.pdf');
});
