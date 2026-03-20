<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('throws when no source is set', function () {
    // Use reflection to create an Image with no sources set
    $image = Image::fromUrl('https://example.com/image.jpg');

    // Clear the URL via reflection to simulate no source
    $ref = new ReflectionProperty($image, 'url');
    $ref->setValue($image, null);

    $image->contents();
})->throws(RuntimeException::class, 'Cannot resolve media source — no source set.');

it('throws when uploaded file has no real path', function () {
    $file = Mockery::mock(UploadedFile::class);
    $file->shouldReceive('getMimeType')->andReturn('image/jpeg');
    $file->shouldReceive('getRealPath')->andReturn(false);

    $image = Image::fromUpload($file);

    $image->contents();
})->throws(RuntimeException::class, 'Cannot resolve uploaded file path.');

it('disk returns null when no disk set', function () {
    $image = Image::fromUrl('https://example.com/image.jpg');

    expect($image->disk())->toBeNull();
});

it('disk returns the disk when set via fromStorage', function () {
    $image = Image::fromStorage('path/to/image.jpg', 's3');

    expect($image->disk())->toBe('s3');
});

it('throws when file path does not exist', function () {
    $image = Image::fromPath('/nonexistent/path/image.jpg');

    $image->contents();
})->throws(ErrorException::class);

it('throws when storage file does not exist', function () {
    Storage::fake('local');

    $image = Image::fromStorage('missing/image.jpg', 'local');

    $image->contents();
})->throws(RuntimeException::class, 'Cannot read file from storage: missing/image.jpg');
