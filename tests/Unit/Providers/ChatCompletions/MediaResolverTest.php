<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Providers\ChatCompletions\MediaResolver;

it('resolves URL input to nested image_url', function () {
    $resolver = new MediaResolver;
    $input = Image::fromUrl('https://example.com/image.png');

    $result = $resolver->resolve($input);

    expect($result)->toBe([
        'type' => 'image_url',
        'image_url' => ['url' => 'https://example.com/image.png'],
    ]);
});

it('resolves base64 input to data URI in nested image_url', function () {
    $resolver = new MediaResolver;
    $input = Image::fromBase64('iVBORw0KGgo=', 'image/png');

    $result = $resolver->resolve($input);

    expect($result['type'])->toBe('image_url');
    expect($result['image_url']['url'])->toBe('data:image/png;base64,iVBORw0KGgo=');
});

it('resolves file path to encoded data URI', function () {
    $resolver = new MediaResolver;
    $tmpFile = tempnam(sys_get_temp_dir(), 'atlas_test_');
    file_put_contents($tmpFile, 'fake-image-data');

    $input = Image::fromPath($tmpFile);
    $result = $resolver->resolve($input);

    expect($result['type'])->toBe('image_url');
    expect($result['image_url']['url'])->toStartWith('data:');
    expect($result['image_url']['url'])->toContain(';base64,');

    unlink($tmpFile);
});
