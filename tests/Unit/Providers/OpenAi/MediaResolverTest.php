<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Document;
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

it('resolves a base64 document to input_file with filename and file_data', function () {
    $resolver = new MediaResolver;
    $input = Document::fromBase64('JVBERi0=', 'application/pdf');

    $result = $resolver->resolve($input);

    expect($result)->toBe([
        'type' => 'input_file',
        'filename' => 'document.pdf',
        'file_data' => 'data:application/pdf;base64,JVBERi0=',
    ]);
});

it('resolves a URL document to input_file with file_url (not downloaded)', function () {
    $resolver = new MediaResolver;
    $input = Document::fromUrl('https://example.com/report.pdf');

    $result = $resolver->resolve($input);

    expect($result)->toBe([
        'type' => 'input_file',
        'file_url' => 'https://example.com/report.pdf',
    ]);
});

it('resolves a document path to input_file with encoded file_data', function () {
    $resolver = new MediaResolver;
    $tempFile = tempnam(sys_get_temp_dir(), 'atlas_doc_');
    file_put_contents($tempFile, 'fake-pdf-bytes');

    $input = Document::fromPath($tempFile, 'application/pdf');
    $result = $resolver->resolve($input);

    expect($result['type'])->toBe('input_file');
    expect($result['filename'])->toBe('document.pdf');
    expect($result['file_data'])->toStartWith('data:application/pdf;base64,');
    expect(base64_decode(str_replace('data:application/pdf;base64,', '', $result['file_data'])))->toBe('fake-pdf-bytes');

    unlink($tempFile);
});

it('derives the filename extension for every supported document mime type', function (string $mime, string $expected) {
    $resolver = new MediaResolver;

    expect($resolver->resolve(Document::fromBase64('eA==', $mime))['filename'])->toBe($expected);
})->with([
    'pdf' => ['application/pdf', 'document.pdf'],
    'txt' => ['text/plain', 'document.txt'],
    'markdown' => ['text/markdown', 'document.md'],
    'html' => ['text/html', 'document.html'],
    'xml (text)' => ['text/xml', 'document.xml'],
    'xml (application)' => ['application/xml', 'document.xml'],
    'csv' => ['text/csv', 'document.csv'],
    'tsv' => ['text/tab-separated-values', 'document.tsv'],
    'json' => ['application/json', 'document.json'],
    'rtf (application)' => ['application/rtf', 'document.rtf'],
    'rtf (text)' => ['text/rtf', 'document.rtf'],
    'doc' => ['application/msword', 'document.doc'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'document.docx'],
    'odt' => ['application/vnd.oasis.opendocument.text', 'document.odt'],
    'xls' => ['application/vnd.ms-excel', 'document.xls'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'document.xlsx'],
    'ppt' => ['application/vnd.ms-powerpoint', 'document.ppt'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'document.pptx'],
]);

it('falls back to the mime subtype, then bin, for unmapped document types', function (string $mime, string $expected) {
    $resolver = new MediaResolver;

    expect($resolver->resolve(Document::fromBase64('eA==', $mime))['filename'])->toBe($expected);
})->with([
    'extension-like subtype is reused' => ['application/yaml', 'document.yaml'],
    'vendor subtype (not a clean token) -> bin' => ['application/vnd.custom-thing', 'document.bin'],
    'empty subtype -> bin' => ['application/', 'document.bin'],
    'mime with no slash -> bin' => ['octet-stream', 'document.bin'],
]);

it('resolves a document file ID to input_file', function () {
    $resolver = new MediaResolver;
    $input = Document::fromFileId('file-doc789');

    $result = $resolver->resolve($input);

    expect($result)->toBe([
        'type' => 'input_file',
        'file_id' => 'file-doc789',
    ]);
});
