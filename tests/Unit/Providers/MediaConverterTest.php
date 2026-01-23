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

// Local path tests

test('convert creates Image from local_path', function () {
    // Create a temporary image file
    $tempFile = sys_get_temp_dir().'/test-image-'.uniqid().'.png';
    // Minimal PNG (1x1 pixel, red)
    $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
    file_put_contents($tempFile, $pngData);

    try {
        $converter = new MediaConverter;

        $result = $converter->convert([
            'type' => 'image',
            'source' => 'local_path',
            'data' => $tempFile,
            'mime_type' => 'image/png',
        ]);

        expect($result)->toBeInstanceOf(Image::class);
    } finally {
        @unlink($tempFile);
    }
});

test('convert creates Document from local_path', function () {
    // Create a temporary text file
    $tempFile = sys_get_temp_dir().'/test-doc-'.uniqid().'.txt';
    file_put_contents($tempFile, 'This is test document content.');

    try {
        $converter = new MediaConverter;

        $result = $converter->convert([
            'type' => 'document',
            'source' => 'local_path',
            'data' => $tempFile,
            'title' => 'Test Doc',
        ]);

        expect($result)->toBeInstanceOf(Document::class);
    } finally {
        @unlink($tempFile);
    }
});

test('convert creates Audio from local_path', function () {
    // Create a temporary audio file (fake data - just needs to exist)
    $tempFile = sys_get_temp_dir().'/test-audio-'.uniqid().'.mp3';
    // Minimal MP3 header (fake, but enough for the path to be valid)
    file_put_contents($tempFile, 'ID3fake-audio-data');

    try {
        $converter = new MediaConverter;

        $result = $converter->convert([
            'type' => 'audio',
            'source' => 'local_path',
            'data' => $tempFile,
            'mime_type' => 'audio/mpeg',
        ]);

        expect($result)->toBeInstanceOf(Audio::class);
    } finally {
        @unlink($tempFile);
    }
});

test('convert creates Video from local_path', function () {
    // Create a temporary video file
    $tempFile = sys_get_temp_dir().'/test-video-'.uniqid().'.mp4';
    file_put_contents($tempFile, 'fake-video-data');

    try {
        $converter = new MediaConverter;

        $result = $converter->convert([
            'type' => 'video',
            'source' => 'local_path',
            'data' => $tempFile,
            'mime_type' => 'video/mp4',
        ]);

        expect($result)->toBeInstanceOf(Video::class);
    } finally {
        @unlink($tempFile);
    }
});

// Storage path tests - these verify that MediaConverter calls the correct Prism factory methods.
// The actual storage integration is tested in sandbox/feature tests since it requires proper Laravel
// filesystem configuration that Prism accesses directly.

test('convert creates Image from storage_path with custom disk', function () {
    // Use a custom disk that we can fake
    Illuminate\Support\Facades\Storage::fake('test-disk');
    Illuminate\Support\Facades\Storage::disk('test-disk')->put('images/test.png', 'fake-image-content');

    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'image',
        'source' => 'storage_path',
        'data' => 'images/test.png',
        'disk' => 'test-disk',
    ]);

    expect($result)->toBeInstanceOf(Image::class);
});

test('convert creates Document from storage_path with disk', function () {
    // Fake a custom disk - this works because Document::fromStoragePath accepts disk param
    Illuminate\Support\Facades\Storage::fake('test-disk');
    Illuminate\Support\Facades\Storage::disk('test-disk')->put('documents/report.pdf', 'fake-pdf-content');

    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'document',
        'source' => 'storage_path',
        'data' => 'documents/report.pdf',
        'disk' => 'test-disk',
        'title' => 'Report',
    ]);

    expect($result)->toBeInstanceOf(Document::class);
});

test('convert creates Document from storage_path without disk uses default', function () {
    // This test verifies that null disk is correctly passed to Prism
    // Actual file access depends on Laravel's default disk configuration
})->skip('Requires Laravel default disk configuration - integration tested in sandbox');

test('convert creates Audio from storage_path with custom disk', function () {
    Illuminate\Support\Facades\Storage::fake('test-disk');
    Illuminate\Support\Facades\Storage::disk('test-disk')->put('audio/speech.mp3', 'fake-audio-content');

    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'audio',
        'source' => 'storage_path',
        'data' => 'audio/speech.mp3',
        'disk' => 'test-disk',
    ]);

    expect($result)->toBeInstanceOf(Audio::class);
});

test('convert creates Video from storage_path with custom disk', function () {
    Illuminate\Support\Facades\Storage::fake('test-disk');
    Illuminate\Support\Facades\Storage::disk('test-disk')->put('videos/clip.mp4', 'fake-video-content');

    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'video',
        'source' => 'storage_path',
        'data' => 'videos/clip.mp4',
        'disk' => 'test-disk',
    ]);

    expect($result)->toBeInstanceOf(Video::class);
});

// File ID tests for Document, Audio, Video

test('convert creates Document from file_id', function () {
    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'document',
        'source' => 'file_id',
        'data' => 'file-doc-123',
        'title' => 'Uploaded Doc',
    ]);

    expect($result)->toBeInstanceOf(Document::class);
});

test('convert creates Audio from file_id', function () {
    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'audio',
        'source' => 'file_id',
        'data' => 'file-audio-456',
    ]);

    expect($result)->toBeInstanceOf(Audio::class);
});

test('convert creates Video from file_id', function () {
    $converter = new MediaConverter;

    $result = $converter->convert([
        'type' => 'video',
        'source' => 'file_id',
        'data' => 'file-video-789',
    ]);

    expect($result)->toBeInstanceOf(Video::class);
});

// Mixed media type conversions

test('convertMany handles mixed media types with different sources', function () {
    // Create temporary files
    $tempImage = sys_get_temp_dir().'/test-mixed-image-'.uniqid().'.png';
    $tempDoc = sys_get_temp_dir().'/test-mixed-doc-'.uniqid().'.txt';
    $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
    file_put_contents($tempImage, $pngData);
    file_put_contents($tempDoc, 'Test document');

    try {
        $converter = new MediaConverter;

        $results = $converter->convertMany([
            // Image from URL
            [
                'type' => 'image',
                'source' => 'url',
                'data' => 'https://example.com/img.jpg',
            ],
            // Document from local path
            [
                'type' => 'document',
                'source' => 'local_path',
                'data' => $tempDoc,
                'title' => 'Local Doc',
            ],
            // Audio from base64
            [
                'type' => 'audio',
                'source' => 'base64',
                'data' => base64_encode('fake-audio'),
                'mime_type' => 'audio/mpeg',
            ],
            // Video from file_id (doesn't require storage)
            [
                'type' => 'video',
                'source' => 'file_id',
                'data' => 'file-video-123',
            ],
            // Image from local path
            [
                'type' => 'image',
                'source' => 'local_path',
                'data' => $tempImage,
                'mime_type' => 'image/png',
            ],
        ]);

        expect($results)->toHaveCount(5);
        expect($results[0])->toBeInstanceOf(Image::class);
        expect($results[1])->toBeInstanceOf(Document::class);
        expect($results[2])->toBeInstanceOf(Audio::class);
        expect($results[3])->toBeInstanceOf(Video::class);
        expect($results[4])->toBeInstanceOf(Image::class);
    } finally {
        @unlink($tempImage);
        @unlink($tempDoc);
    }
});
