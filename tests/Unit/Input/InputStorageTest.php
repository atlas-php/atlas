<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Audio;
use Atlasphp\Atlas\Input\Document;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Input\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// ─── fromUpload ──────────────────────────────────────────────────────────────

it('creates Image from upload', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    $image = Image::fromUpload($file);

    expect($image->isUpload())->toBeTrue();
    expect($image->mimeType())->toBe('image/jpeg');
});

it('reads contents from uploaded file', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 10, 10);
    $image = Image::fromUpload($file);

    expect(strlen($image->contents()))->toBeGreaterThan(0);
});

it('stores uploaded file and updates reference', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $file = UploadedFile::fake()->image('photo.jpg');
    $image = Image::fromUpload($file);

    $path = $image->storeAs('uploads/photo.jpg');

    expect($path)->toBe('uploads/photo.jpg');
    expect($image->isStorage())->toBeTrue();
    expect($image->isUpload())->toBeFalse();
    expect($image->storagePath())->toBe('uploads/photo.jpg');
    Storage::disk('local')->assertExists('uploads/photo.jpg');
});

// ─── store() updates internal state ──────────────────────────────────────────

it('clears original sources after store', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $image = Image::fromBase64(base64_encode('data'), 'image/png');

    expect($image->isBase64())->toBeTrue();

    $image->store();

    expect($image->isBase64())->toBeFalse();
    expect($image->isStorage())->toBeTrue();
    expect($image->isUrl())->toBeFalse();
    expect($image->isPath())->toBeFalse();
    expect($image->isUpload())->toBeFalse();
});

it('reads from storage after store instead of original source', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $image = Image::fromBase64(base64_encode('original-data'), 'image/png');
    $path = $image->store();

    // Verify it reads from storage now
    $contents = $image->contents();
    expect($contents)->toBe('original-data');

    // Modify storage contents to prove it reads from there
    Storage::disk('local')->put($path, 'modified-data');
    expect($image->contents())->toBe('modified-data');
});

// ─── fromStorage ─────────────────────────────────────────────────────────────

it('fromStorage sets isStorage true and isPath false', function () {
    $image = Image::fromStorage('photos/test.jpg', 's3');

    expect($image->isStorage())->toBeTrue();
    expect($image->isPath())->toBeFalse();
    expect($image->storagePath())->toBe('photos/test.jpg');
    expect($image->storageDisk())->toBe('s3');
});

it('fromStorage reads contents from disk', function () {
    Storage::fake('local');
    Storage::disk('local')->put('images/test.png', 'stored-image');

    $image = Image::fromStorage('images/test.png', 'local');

    expect($image->contents())->toBe('stored-image');
});

// ─── fromPath → store ────────────────────────────────────────────────────────

it('stores from path and switches to storage reference', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $tmp = tempnam(sys_get_temp_dir(), 'atlas_');
    file_put_contents($tmp, 'path-content');

    $image = Image::fromPath($tmp);
    $path = $image->storeAs('persisted/image.jpg');

    expect($image->isStorage())->toBeTrue();
    expect($image->isPath())->toBeFalse();
    Storage::disk('local')->assertExists('persisted/image.jpg');

    unlink($tmp);
});

// ─── fromUrl → store ─────────────────────────────────────────────────────────

it('stores from url and switches to storage reference', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
    Http::fake(['example.com/*' => Http::response('url-image-data')]);

    $image = Image::fromUrl('https://example.com/photo.jpg');
    $path = $image->storeAs('downloads/photo.jpg');

    expect($image->isStorage())->toBeTrue();
    expect($image->isUrl())->toBeFalse();
    Storage::disk('local')->assertExists('downloads/photo.jpg');
    expect($image->contents())->toBe('url-image-data');
});

// ─── All types have fromUpload ───────────────────────────────────────────────

it('all input types support fromUpload', function () {
    $file = UploadedFile::fake()->create('test.bin', 1);

    expect(Image::fromUpload($file)->isUpload())->toBeTrue();
    expect(Audio::fromUpload($file)->isUpload())->toBeTrue();
    expect(Video::fromUpload($file)->isUpload())->toBeTrue();
    expect(Document::fromUpload($file)->isUpload())->toBeTrue();
});

// ─── defaultExtension ────────────────────────────────────────────────────────

it('Image uses correct default extensions', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $jpg = Image::fromBase64(base64_encode('x'), 'image/jpeg');
    $png = Image::fromBase64(base64_encode('x'), 'image/png');

    $jpgPath = $jpg->store();
    $pngPath = $png->store();

    expect($jpgPath)->toEndWith('.jpg');
    expect($pngPath)->toEndWith('.png');
});

it('Audio uses correct default extensions', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $mp3 = Audio::fromBase64(base64_encode('x'), 'audio/mpeg');
    $wav = Audio::fromBase64(base64_encode('x'), 'audio/wav');

    expect($mp3->store())->toEndWith('.mp3');
    expect($wav->store())->toEndWith('.wav');
});

it('storePublicly updates reference', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $image = Image::fromBase64(base64_encode('data'), 'image/png');
    $path = $image->storePublicly();

    expect($image->isStorage())->toBeTrue();
    Storage::disk('local')->assertExists($path);
});
