<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Services\MediaConverter;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;

test('convert creates Image from URL', function () {
    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'image',
        'source' => 'url',
        'data' => 'https://example.com/image.jpg',
    ]);

    expect($result)->toBeInstanceOf(Image::class);
});

test('convert creates Image from base64', function () {
    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'image',
        'source' => 'base64',
        'data' => base64_encode('fake-image-data'),
        'mime_type' => 'image/jpeg',
    ]);

    expect($result)->toBeInstanceOf(Image::class);
});

test('convert creates Document from URL', function () {
    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'document',
        'source' => 'url',
        'data' => 'https://example.com/doc.pdf',
        'title' => 'Test Document',
    ]);

    expect($result)->toBeInstanceOf(Document::class);
});

test('convert creates Audio from URL', function () {
    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'audio',
        'source' => 'url',
        'data' => 'https://example.com/audio.mp3',
    ]);

    expect($result)->toBeInstanceOf(Audio::class);
});

test('convert creates Video from URL', function () {
    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'video',
        'source' => 'url',
        'data' => 'https://example.com/video.mp4',
    ]);

    expect($result)->toBeInstanceOf(Video::class);
});

test('convert throws exception when type is missing', function () {
    $converter = new MediaConverter;

    $converter->convert([
        'source' => 'url',
        'data' => 'https://example.com/image.jpg',
    ]);
})->throws(InvalidArgumentException::class, 'Attachment must have a "type" field.');

test('convert throws exception when source is missing', function () {
    $converter = new MediaConverter;

    $converter->convert([
        'type' => 'image',
        'data' => 'https://example.com/image.jpg',
    ]);
})->throws(InvalidArgumentException::class, 'Attachment must have a "source" field.');

test('convert throws exception when data is missing', function () {
    $converter = new MediaConverter;

    $converter->convert([
        'type' => 'image',
        'source' => 'url',
    ]);
})->throws(InvalidArgumentException::class, 'Attachment must have a "data" field.');

test('convert throws exception for invalid type', function () {
    $converter = new MediaConverter;

    $converter->convert([
        'type' => 'invalid',
        'source' => 'url',
        'data' => 'https://example.com/file.txt',
    ]);
})->throws(ValueError::class);

test('convert throws exception for invalid source', function () {
    $converter = new MediaConverter;

    $converter->convert([
        'type' => 'image',
        'source' => 'invalid',
        'data' => 'some-data',
    ]);
})->throws(ValueError::class);

test('convertMany converts multiple attachments', function () {
    $converter = new MediaConverter;

    $results = $converter->convertMany([
        [
            'type' => 'image',
            'source' => 'url',
            'data' => 'https://example.com/image1.jpg',
        ],
        [
            'type' => 'image',
            'source' => 'url',
            'data' => 'https://example.com/image2.jpg',
        ],
        [
            'type' => 'document',
            'source' => 'url',
            'data' => 'https://example.com/doc.pdf',
        ],
    ]);

    expect($results)->toHaveCount(3);
    expect($results[0])->toBeInstanceOf(Image::class);
    expect($results[1])->toBeInstanceOf(Image::class);
    expect($results[2])->toBeInstanceOf(Document::class);
});

test('convertMany returns empty array for empty input', function () {
    $converter = new MediaConverter;

    $results = $converter->convertMany([]);

    expect($results)->toBe([]);
});

test('convert creates Image from file_id', function () {
    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'image',
        'source' => 'file_id',
        'data' => 'file-abc123',
    ]);

    expect($result)->toBeInstanceOf(Image::class);
});

test('convert creates Document from base64 with mime type', function () {
    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'document',
        'source' => 'base64',
        'data' => base64_encode('fake-pdf-data'),
        'mime_type' => 'application/pdf',
        'title' => 'Report',
    ]);

    expect($result)->toBeInstanceOf(Document::class);
});

test('convert creates Audio from base64', function () {
    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'audio',
        'source' => 'base64',
        'data' => base64_encode('fake-audio-data'),
        'mime_type' => 'audio/mp3',
    ]);

    expect($result)->toBeInstanceOf(Audio::class);
});

test('convert creates Video from base64', function () {
    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'video',
        'source' => 'base64',
        'data' => base64_encode('fake-video-data'),
        'mime_type' => 'video/mp4',
    ]);

    expect($result)->toBeInstanceOf(Video::class);
});
