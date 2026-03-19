<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Audio;
use Atlasphp\Atlas\Input\Document;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Input\Video;

it('creates an Image from URL', function () {
    $image = Image::fromUrl('https://example.com/image.jpg');

    expect($image->isUrl())->toBeTrue();
    expect($image->isPath())->toBeFalse();
    expect($image->isBase64())->toBeFalse();
    expect($image->isFileId())->toBeFalse();
    expect($image->url())->toBe('https://example.com/image.jpg');
});

it('creates an Image from path', function () {
    $image = Image::fromPath('/tmp/image.jpg');

    expect($image->isPath())->toBeTrue();
    expect($image->path())->toBe('/tmp/image.jpg');
});

it('creates an Image from base64', function () {
    $image = Image::fromBase64('abc123', 'image/png');

    expect($image->isBase64())->toBeTrue();
    expect($image->data())->toBe('abc123');
    expect($image->mimeType())->toBe('image/png');
});

it('creates an Image from file ID', function () {
    $image = Image::fromFileId('file-abc');

    expect($image->isFileId())->toBeTrue();
    expect($image->fileId())->toBe('file-abc');
});

it('creates an Image from storage', function () {
    $image = Image::fromStorage('photos/test.jpg', 's3');

    expect($image->isPath())->toBeTrue();
    expect($image->path())->toBe('photos/test.jpg');
    expect($image->disk())->toBe('s3');
});

it('returns correct default mime types', function () {
    expect(Image::fromUrl('x')->mimeType())->toBe('image/jpeg');
    expect(Audio::fromUrl('x')->mimeType())->toBe('audio/mpeg');
    expect(Video::fromUrl('x')->mimeType())->toBe('video/mp4');
    expect(Document::fromUrl('x')->mimeType())->toBe('application/pdf');
});

it('creates Audio from URL', function () {
    $audio = Audio::fromUrl('https://example.com/audio.mp3');

    expect($audio->isUrl())->toBeTrue();
    expect($audio->url())->toBe('https://example.com/audio.mp3');
});

it('creates Video from base64', function () {
    $video = Video::fromBase64('data', 'video/webm');

    expect($video->isBase64())->toBeTrue();
    expect($video->mimeType())->toBe('video/webm');
});

it('creates Document from file ID', function () {
    $doc = Document::fromFileId('doc-123');

    expect($doc->isFileId())->toBeTrue();
    expect($doc->fileId())->toBe('doc-123');
});
