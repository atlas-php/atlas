<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Providers\OpenAi\MediaResolver;

it('resolves URL input to input_image', function () {
    $resolver = new MediaResolver;
    $input = Image::fromUrl('https://example.com/photo.jpg');

    $result = $resolver->resolve($input);

    expect($result)->toBe([
        'type' => 'input_image',
        'image_url' => 'https://example.com/photo.jpg',
    ]);
});

it('resolves base64 input to input_image with data URI', function () {
    $resolver = new MediaResolver;
    $input = Image::fromBase64('aGVsbG8=', 'image/png');

    $result = $resolver->resolve($input);

    expect($result)->toBe([
        'type' => 'input_image',
        'image_url' => 'data:image/png;base64,aGVsbG8=',
    ]);
});

it('resolves file ID to input_file', function () {
    $resolver = new MediaResolver;
    $input = Image::fromFileId('file-abc123');

    $result = $resolver->resolve($input);

    expect($result)->toBe([
        'type' => 'input_file',
        'file_id' => 'file-abc123',
    ]);
});

it('resolves file path to input_image with encoded data', function () {
    $resolver = new MediaResolver;
    $tempFile = tempnam(sys_get_temp_dir(), 'atlas_test_');
    file_put_contents($tempFile, 'fake-image-data');

    $input = Image::fromPath($tempFile);
    $result = $resolver->resolve($input);

    expect($result['type'])->toBe('input_image');
    expect($result['image_url'])->toStartWith('data:image/jpeg;base64,');
    expect(base64_decode(str_replace('data:image/jpeg;base64,', '', $result['image_url'])))->toBe('fake-image-data');

    unlink($tempFile);
});
