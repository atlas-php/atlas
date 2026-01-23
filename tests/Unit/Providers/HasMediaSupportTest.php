<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Enums\MediaSource;
use Atlasphp\Atlas\Providers\Support\HasMediaSupport;

/**
 * Test class that uses the HasMediaSupport trait.
 */
class TestMediaClass
{
    use HasMediaSupport;

    /**
     * Expose the protected method for testing.
     *
     * @return array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>
     */
    public function exposeGetCurrentAttachments(): array
    {
        return $this->getCurrentAttachments();
    }
}

test('withImage returns a clone with image attachment', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withImage('https://example.com/image.jpg');

    expect($clone)->not->toBe($instance);
    expect($clone)->toBeInstanceOf(TestMediaClass::class);
});

test('withImage adds URL image attachment', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withImage('https://example.com/image.jpg');

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['type'])->toBe('image');
    expect($attachments[0]['source'])->toBe('url');
    expect($attachments[0]['data'])->toBe('https://example.com/image.jpg');
});

test('withImage adds base64 image attachment', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withImage(
        'base64data',
        MediaSource::Base64,
        'image/png'
    );

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['type'])->toBe('image');
    expect($attachments[0]['source'])->toBe('base64');
    expect($attachments[0]['data'])->toBe('base64data');
    expect($attachments[0]['mime_type'])->toBe('image/png');
});

test('withImage adds multiple images from array', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withImage([
        'https://example.com/image1.jpg',
        'https://example.com/image2.jpg',
    ]);

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments)->toHaveCount(2);
    expect($attachments[0]['data'])->toBe('https://example.com/image1.jpg');
    expect($attachments[1]['data'])->toBe('https://example.com/image2.jpg');
});

test('withDocument adds document attachment', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withDocument(
        'https://example.com/doc.pdf',
        MediaSource::Url,
        null,
        'Report Title'
    );

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['type'])->toBe('document');
    expect($attachments[0]['source'])->toBe('url');
    expect($attachments[0]['data'])->toBe('https://example.com/doc.pdf');
    expect($attachments[0]['title'])->toBe('Report Title');
});

test('withDocument adds storage path document with disk', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withDocument(
        'documents/report.pdf',
        MediaSource::StoragePath,
        null,
        'Report',
        's3'
    );

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['type'])->toBe('document');
    expect($attachments[0]['source'])->toBe('storage_path');
    expect($attachments[0]['data'])->toBe('documents/report.pdf');
    expect($attachments[0]['title'])->toBe('Report');
    expect($attachments[0]['disk'])->toBe('s3');
});

test('withAudio adds audio attachment', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withAudio('https://example.com/audio.mp3');

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['type'])->toBe('audio');
    expect($attachments[0]['source'])->toBe('url');
    expect($attachments[0]['data'])->toBe('https://example.com/audio.mp3');
});

test('withVideo adds video attachment', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withVideo('https://example.com/video.mp4');

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['type'])->toBe('video');
    expect($attachments[0]['source'])->toBe('url');
    expect($attachments[0]['data'])->toBe('https://example.com/video.mp4');
});

test('original instance is not modified', function () {
    $instance = new TestMediaClass;
    $instance->withImage('https://example.com/image.jpg');

    expect($instance->exposeGetCurrentAttachments())->toBe([]);
});

test('chaining different media types accumulates attachments', function () {
    $instance = new TestMediaClass;
    $clone = $instance
        ->withImage('https://example.com/image.jpg')
        ->withDocument('https://example.com/doc.pdf')
        ->withAudio('https://example.com/audio.mp3');

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments)->toHaveCount(3);
    expect($attachments[0]['type'])->toBe('image');
    expect($attachments[1]['type'])->toBe('document');
    expect($attachments[2]['type'])->toBe('audio');
});

test('getCurrentAttachments returns empty array when no attachments', function () {
    $instance = new TestMediaClass;

    expect($instance->exposeGetCurrentAttachments())->toBe([]);
});

test('withImage with file_id source', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withImage('file-abc123', MediaSource::FileId);

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['source'])->toBe('file_id');
    expect($attachments[0]['data'])->toBe('file-abc123');
});

test('withImage with local_path source', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withImage('/path/to/image.jpg', MediaSource::LocalPath);

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['source'])->toBe('local_path');
    expect($attachments[0]['data'])->toBe('/path/to/image.jpg');
});

test('mime type is only added when provided', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withImage('https://example.com/image.jpg');

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments[0])->not->toHaveKey('mime_type');
});

test('title is only added when provided', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withDocument('https://example.com/doc.pdf');

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments[0])->not->toHaveKey('title');
});

test('disk is only added when provided', function () {
    $instance = new TestMediaClass;
    $clone = $instance->withDocument('doc.pdf', MediaSource::StoragePath);

    $attachments = $clone->exposeGetCurrentAttachments();

    expect($attachments[0])->not->toHaveKey('disk');
});
