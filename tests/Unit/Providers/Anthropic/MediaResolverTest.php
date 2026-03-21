<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Document;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Anthropic\MediaResolver;

it('resolves image URL as url source type', function () {
    $resolver = new MediaResolver;
    $input = Image::fromUrl('https://example.com/photo.jpg');

    $result = $resolver->resolve($input);

    expect($result)->toBe([
        'type' => 'image',
        'source' => [
            'type' => 'url',
            'url' => 'https://example.com/photo.jpg',
        ],
    ]);
});

it('resolves image base64 as base64 source type', function () {
    $resolver = new MediaResolver;
    $input = Image::fromBase64('abc123data', 'image/png');

    $result = $resolver->resolve($input);

    expect($result)->toBe([
        'type' => 'image',
        'source' => [
            'type' => 'base64',
            'media_type' => 'image/png',
            'data' => 'abc123data',
        ],
    ]);
});

it('resolves document base64 as document block', function () {
    $resolver = new MediaResolver;
    $input = Document::fromBase64('pdfdata123', 'application/pdf');

    $result = $resolver->resolve($input);

    expect($result)->toBe([
        'type' => 'document',
        'source' => [
            'type' => 'base64',
            'media_type' => 'application/pdf',
            'data' => 'pdfdata123',
        ],
    ]);
});

it('resolves image from path as base64', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'atlas_test_');
    file_put_contents($tmpFile, 'fake-image-bytes');

    $resolver = new MediaResolver;
    $input = Image::fromPath($tmpFile);

    $result = $resolver->resolve($input);

    expect($result['type'])->toBe('image');
    expect($result['source']['type'])->toBe('base64');
    expect($result['source']['media_type'])->toBe('image/jpeg');
    expect(base64_decode($result['source']['data']))->toBe('fake-image-bytes');

    unlink($tmpFile);
});

it('resolves document from path as base64 document block', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'atlas_test_');
    file_put_contents($tmpFile, 'fake-pdf-bytes');

    $resolver = new MediaResolver;
    $input = Document::fromPath($tmpFile);

    $result = $resolver->resolve($input);

    expect($result['type'])->toBe('document');
    expect($result['source']['type'])->toBe('base64');
    expect($result['source']['media_type'])->toBe('application/pdf');
    expect(base64_decode($result['source']['data']))->toBe('fake-pdf-bytes');

    unlink($tmpFile);
});

it('resolves document URL as base64 document block via fetch', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'atlas_test_');
    file_put_contents($tmpFile, 'fetched-doc-content');

    $resolver = new MediaResolver;
    // Use file:// URL to avoid real HTTP — Document URLs go through file_get_contents
    $input = Document::fromUrl('file://'.$tmpFile);

    $result = $resolver->resolve($input);

    expect($result['type'])->toBe('document');
    expect($result['source']['type'])->toBe('base64');
    expect(base64_decode($result['source']['data']))->toBe('fetched-doc-content');

    unlink($tmpFile);
});

it('throws for input with no source set', function () {
    $resolver = new MediaResolver;
    // Create a mock Input with no source
    $input = Mockery::mock(Input::class);
    $input->shouldReceive('isUrl')->andReturn(false);
    $input->shouldReceive('isBase64')->andReturn(false);
    $input->shouldReceive('isPath')->andReturn(false);

    $resolver->resolve($input);
})->throws(InvalidArgumentException::class, 'no source set');

it('throws when path file does not exist', function () {
    $resolver = new MediaResolver;
    $input = Image::fromPath('/nonexistent/path/to/image.jpg');

    $resolver->resolve($input);
})->throws(InvalidArgumentException::class, 'Failed to read media from path');
