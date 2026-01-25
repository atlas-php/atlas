<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Services\MediaConverter;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;

beforeEach(function () {
    $this->converter = new MediaConverter;
});

// =============================================================================
// Image Tests
// =============================================================================

test('convert creates Image from url source', function () {
    $attachment = [
        'type' => 'image',
        'source' => 'url',
        'data' => 'https://example.com/image.jpg',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Image::class);
});

test('convert creates Image from base64 source', function () {
    $attachment = [
        'type' => 'image',
        'source' => 'base64',
        'data' => base64_encode('fake-image-data'),
        'mime_type' => 'image/png',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Image::class);
});

test('convert creates Image from file_id source', function () {
    $attachment = [
        'type' => 'image',
        'source' => 'file_id',
        'data' => 'file-abc123',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Image::class);
});

// =============================================================================
// Document Tests
// =============================================================================

test('convert creates Document from url source', function () {
    $attachment = [
        'type' => 'document',
        'source' => 'url',
        'data' => 'https://example.com/document.pdf',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Document::class);
});

test('convert creates Document from base64 source', function () {
    $attachment = [
        'type' => 'document',
        'source' => 'base64',
        'data' => base64_encode('fake-pdf-data'),
        'mime_type' => 'application/pdf',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Document::class);
});

test('convert creates Document from file_id source', function () {
    $attachment = [
        'type' => 'document',
        'source' => 'file_id',
        'data' => 'file-doc123',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Document::class);
});

test('convert creates Document with title', function () {
    $attachment = [
        'type' => 'document',
        'source' => 'url',
        'data' => 'https://example.com/report.pdf',
        'title' => 'Monthly Report',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Document::class);
});

// =============================================================================
// Audio Tests
// =============================================================================

test('convert creates Audio from url source', function () {
    $attachment = [
        'type' => 'audio',
        'source' => 'url',
        'data' => 'https://example.com/audio.mp3',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Audio::class);
});

test('convert creates Audio from url source with mime type', function () {
    $attachment = [
        'type' => 'audio',
        'source' => 'url',
        'data' => 'https://example.com/audio.wav',
        'mime_type' => 'audio/wav',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Audio::class);
});

test('convert creates Audio from base64 source', function () {
    $attachment = [
        'type' => 'audio',
        'source' => 'base64',
        'data' => base64_encode('fake-audio-data'),
        'mime_type' => 'audio/mp3',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Audio::class);
});

test('convert creates Audio from file_id source', function () {
    $attachment = [
        'type' => 'audio',
        'source' => 'file_id',
        'data' => 'file-audio123',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Audio::class);
});

// =============================================================================
// Video Tests
// =============================================================================

test('convert creates Video from url source', function () {
    $attachment = [
        'type' => 'video',
        'source' => 'url',
        'data' => 'https://example.com/video.mp4',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Video::class);
});

test('convert creates Video from url source with mime type', function () {
    $attachment = [
        'type' => 'video',
        'source' => 'url',
        'data' => 'https://example.com/video.webm',
        'mime_type' => 'video/webm',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Video::class);
});

test('convert creates Video from base64 source', function () {
    $attachment = [
        'type' => 'video',
        'source' => 'base64',
        'data' => base64_encode('fake-video-data'),
        'mime_type' => 'video/mp4',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Video::class);
});

test('convert creates Video from file_id source', function () {
    $attachment = [
        'type' => 'video',
        'source' => 'file_id',
        'data' => 'file-video123',
    ];

    $result = $this->converter->convert($attachment);

    expect($result)->toBeInstanceOf(Video::class);
});

// =============================================================================
// Validation Tests
// =============================================================================

test('convert throws for missing type field', function () {
    $attachment = [
        'source' => 'url',
        'data' => 'https://example.com/file.jpg',
    ];

    $this->converter->convert($attachment);
})->throws(InvalidArgumentException::class, 'Attachment must have a "type" field.');

test('convert throws for missing source field', function () {
    $attachment = [
        'type' => 'image',
        'data' => 'https://example.com/file.jpg',
    ];

    $this->converter->convert($attachment);
})->throws(InvalidArgumentException::class, 'Attachment must have a "source" field.');

test('convert throws for missing data field', function () {
    $attachment = [
        'type' => 'image',
        'source' => 'url',
    ];

    $this->converter->convert($attachment);
})->throws(InvalidArgumentException::class, 'Attachment must have a "data" field.');

test('convert throws for unknown type', function () {
    $attachment = [
        'type' => 'unknown',
        'source' => 'url',
        'data' => 'https://example.com/file.xyz',
    ];

    $this->converter->convert($attachment);
})->throws(InvalidArgumentException::class, 'Unknown attachment type: unknown');

// =============================================================================
// convertMany Tests
// =============================================================================

test('convertMany converts multiple attachments', function () {
    $attachments = [
        [
            'type' => 'image',
            'source' => 'url',
            'data' => 'https://example.com/image.jpg',
        ],
        [
            'type' => 'document',
            'source' => 'url',
            'data' => 'https://example.com/document.pdf',
        ],
        [
            'type' => 'audio',
            'source' => 'url',
            'data' => 'https://example.com/audio.mp3',
        ],
        [
            'type' => 'video',
            'source' => 'url',
            'data' => 'https://example.com/video.mp4',
        ],
    ];

    $results = $this->converter->convertMany($attachments);

    expect($results)->toHaveCount(4);
    expect($results[0])->toBeInstanceOf(Image::class);
    expect($results[1])->toBeInstanceOf(Document::class);
    expect($results[2])->toBeInstanceOf(Audio::class);
    expect($results[3])->toBeInstanceOf(Video::class);
});

test('convertMany returns empty array for empty input', function () {
    $results = $this->converter->convertMany([]);

    expect($results)->toBe([]);
});

test('convertMany handles mixed source types', function () {
    $attachments = [
        [
            'type' => 'audio',
            'source' => 'url',
            'data' => 'https://example.com/audio.mp3',
        ],
        [
            'type' => 'audio',
            'source' => 'base64',
            'data' => base64_encode('audio-data'),
            'mime_type' => 'audio/wav',
        ],
        [
            'type' => 'video',
            'source' => 'file_id',
            'data' => 'file-vid123',
        ],
    ];

    $results = $this->converter->convertMany($attachments);

    expect($results)->toHaveCount(3);
    expect($results[0])->toBeInstanceOf(Audio::class);
    expect($results[1])->toBeInstanceOf(Audio::class);
    expect($results[2])->toBeInstanceOf(Video::class);
});
