<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Google\MediaResolver;

// ─── URL Sources ────────────────────────────────────────────────────────────

it('resolves a regular URL as inline_data', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'atlas_url_test_');
    file_put_contents($tmpFile, 'fake-image-bytes');

    $input = Image::fromUrl('file://'.$tmpFile);
    $resolver = new MediaResolver;
    $result = $resolver->resolve($input);

    expect($result)->toHaveKey('inline_data')
        ->and($result['inline_data']['mime_type'])->toBe('image/jpeg')
        ->and($result['inline_data']['data'])->toBe(base64_encode('fake-image-bytes'));

    unlink($tmpFile);
});

it('resolves a gs:// URI as file_data', function () {
    $input = Image::fromUrl('gs://my-bucket/photo.jpg');
    $resolver = new MediaResolver;
    $result = $resolver->resolve($input);

    expect($result)->toHaveKey('file_data')
        ->and($result['file_data']['mime_type'])->toBe('image/jpeg')
        ->and($result['file_data']['file_uri'])->toBe('gs://my-bucket/photo.jpg');
});

// ─── Base64 Source ──────────────────────────────────────────────────────────

it('resolves base64 input as inline_data', function () {
    $input = Image::fromBase64('abc123data', 'image/png');
    $resolver = new MediaResolver;
    $result = $resolver->resolve($input);

    expect($result)->toHaveKey('inline_data')
        ->and($result['inline_data']['mime_type'])->toBe('image/png')
        ->and($result['inline_data']['data'])->toBe('abc123data');
});

// ─── Path Source ────────────────────────────────────────────────────────────

it('resolves a local file path as inline_data', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'atlas_test_');
    file_put_contents($tmpFile, 'local-file-bytes');

    $input = Image::fromPath($tmpFile);
    $resolver = new MediaResolver;
    $result = $resolver->resolve($input);

    expect($result)->toHaveKey('inline_data')
        ->and($result['inline_data']['mime_type'])->toBe('image/jpeg')
        ->and($result['inline_data']['data'])->toBe(base64_encode('local-file-bytes'));

    unlink($tmpFile);
});

// ─── File ID Source ─────────────────────────────────────────────────────────

it('resolves a file ID as file_data', function () {
    $input = Image::fromFileId('files/abc-123');
    $resolver = new MediaResolver;
    $result = $resolver->resolve($input);

    expect($result)->toHaveKey('file_data')
        ->and($result['file_data']['mime_type'])->toBe('image/jpeg')
        ->and($result['file_data']['file_uri'])->toBe('files/abc-123');
});

// ─── Unsupported Source ─────────────────────────────────────────────────────

it('throws InvalidArgumentException when no source is set', function () {
    // Create an Input with no source via reflection
    $input = new class extends Input
    {
        public function mimeType(): string
        {
            return 'image/jpeg';
        }

        protected function defaultExtension(): string
        {
            return 'jpg';
        }
    };

    $resolver = new MediaResolver;
    $resolver->resolve($input);
})->throws(InvalidArgumentException::class, 'Cannot resolve media input');
