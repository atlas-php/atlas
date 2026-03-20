<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Audio;
use Atlasphp\Atlas\Input\Document;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Input\Video;
use Illuminate\Support\Facades\Storage;

// ─── Audio factory methods ──────────────────────────────────────────────────

it('creates Audio from path', function () {
    $audio = Audio::fromPath('/tmp/audio.mp3');

    expect($audio->isPath())->toBeTrue();
    expect($audio->path())->toBe('/tmp/audio.mp3');
});

it('creates Audio from storage', function () {
    $audio = Audio::fromStorage('recordings/call.mp3', 's3');

    expect($audio->isStorage())->toBeTrue();
    expect($audio->storagePath())->toBe('recordings/call.mp3');
    expect($audio->storageDisk())->toBe('s3');
    expect($audio->disk())->toBe('s3');
});

it('creates Audio from storage with null disk', function () {
    $audio = Audio::fromStorage('recordings/call.mp3');

    expect($audio->isStorage())->toBeTrue();
    expect($audio->storagePath())->toBe('recordings/call.mp3');
    expect($audio->disk())->toBeNull();
    expect($audio->storageDisk())->toBeNull();
});

it('creates Audio from storage and reads contents', function () {
    Storage::fake('local');
    Storage::disk('local')->put('audio/test.mp3', 'audio-data');

    $audio = Audio::fromStorage('audio/test.mp3', 'local');

    expect($audio->contents())->toBe('audio-data');
});

it('creates Audio from base64', function () {
    $audio = Audio::fromBase64('encoded-audio', 'audio/wav');

    expect($audio->isBase64())->toBeTrue();
    expect($audio->data())->toBe('encoded-audio');
    expect($audio->mimeType())->toBe('audio/wav');
});

it('creates Audio from file ID', function () {
    $audio = Audio::fromFileId('audio-file-123');

    expect($audio->isFileId())->toBeTrue();
    expect($audio->fileId())->toBe('audio-file-123');
});

// ─── Document factory methods ───────────────────────────────────────────────

it('creates Document from path', function () {
    $doc = Document::fromPath('/tmp/report.pdf');

    expect($doc->isPath())->toBeTrue();
    expect($doc->path())->toBe('/tmp/report.pdf');
});

it('creates Document from storage', function () {
    $doc = Document::fromStorage('docs/report.pdf', 'local');

    expect($doc->isStorage())->toBeTrue();
    expect($doc->storagePath())->toBe('docs/report.pdf');
    expect($doc->disk())->toBe('local');
});

it('creates Document from storage and reads contents', function () {
    Storage::fake('local');
    Storage::disk('local')->put('docs/test.pdf', 'pdf-data');

    $doc = Document::fromStorage('docs/test.pdf', 'local');

    expect($doc->contents())->toBe('pdf-data');
});

it('creates Document from base64', function () {
    $doc = Document::fromBase64('encoded-pdf', 'application/pdf');

    expect($doc->isBase64())->toBeTrue();
    expect($doc->data())->toBe('encoded-pdf');
    expect($doc->mimeType())->toBe('application/pdf');
});

it('creates Document from file ID', function () {
    $doc = Document::fromFileId('doc-456');

    expect($doc->isFileId())->toBeTrue();
    expect($doc->fileId())->toBe('doc-456');
});

it('creates Document from URL', function () {
    $doc = Document::fromUrl('https://example.com/report.pdf');

    expect($doc->isUrl())->toBeTrue();
    expect($doc->url())->toBe('https://example.com/report.pdf');
});

// ─── Video factory methods ──────────────────────────────────────────────────

it('creates Video from path', function () {
    $video = Video::fromPath('/tmp/clip.mp4');

    expect($video->isPath())->toBeTrue();
    expect($video->path())->toBe('/tmp/clip.mp4');
});

it('creates Video from storage', function () {
    $video = Video::fromStorage('videos/clip.mp4', 's3');

    expect($video->isStorage())->toBeTrue();
    expect($video->storagePath())->toBe('videos/clip.mp4');
    expect($video->disk())->toBe('s3');
});

it('creates Video from storage and reads contents', function () {
    Storage::fake('local');
    Storage::disk('local')->put('videos/test.mp4', 'video-data');

    $video = Video::fromStorage('videos/test.mp4', 'local');

    expect($video->contents())->toBe('video-data');
});

it('creates Video from base64', function () {
    $video = Video::fromBase64('encoded-video', 'video/quicktime');

    expect($video->isBase64())->toBeTrue();
    expect($video->data())->toBe('encoded-video');
    expect($video->mimeType())->toBe('video/quicktime');
});

it('creates Video from file ID', function () {
    $video = Video::fromFileId('video-789');

    expect($video->isFileId())->toBeTrue();
    expect($video->fileId())->toBe('video-789');
});

it('creates Video from URL', function () {
    $video = Video::fromUrl('https://example.com/clip.mp4');

    expect($video->isUrl())->toBeTrue();
    expect($video->url())->toBe('https://example.com/clip.mp4');
});

// ─── Image factory methods (storage + fileId) ───────────────────────────────

it('creates Image from storage with null disk', function () {
    $image = Image::fromStorage('photos/test.jpg');

    expect($image->isStorage())->toBeTrue();
    expect($image->disk())->toBeNull();
});

it('creates Image from storage and reads contents', function () {
    Storage::fake('local');
    Storage::disk('local')->put('images/test.jpg', 'image-data');

    $image = Image::fromStorage('images/test.jpg', 'local');

    expect($image->contents())->toBe('image-data');
});
