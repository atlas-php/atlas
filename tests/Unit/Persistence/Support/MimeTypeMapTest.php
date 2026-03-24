<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Support\MimeTypeMap;

// ─── toExtension ───────────────────────────────────────────────

it('resolves extension from known MIME type', function (string $mimeType, string $expected) {
    expect(MimeTypeMap::toExtension($mimeType))->toBe($expected);
})->with([
    ['image/png', 'png'],
    ['image/jpeg', 'jpg'],
    ['image/gif', 'gif'],
    ['image/webp', 'webp'],
    ['image/svg+xml', 'svg'],
    ['audio/mpeg', 'mp3'],
    ['audio/wav', 'wav'],
    ['audio/ogg', 'ogg'],
    ['audio/flac', 'flac'],
    ['video/mp4', 'mp4'],
    ['video/webm', 'webm'],
    ['application/pdf', 'pdf'],
    ['application/json', 'json'],
    ['text/plain', 'txt'],
    ['text/csv', 'csv'],
]);

it('falls back to asset type default extension when MIME is unknown', function (AssetType $assetType, string $expected) {
    expect(MimeTypeMap::toExtension('application/octet-stream', $assetType))->toBe($expected);
})->with([
    [AssetType::Image, 'png'],
    [AssetType::Audio, 'mp3'],
    [AssetType::Video, 'mp4'],
    [AssetType::Document, 'bin'],
    [AssetType::File, 'bin'],
]);

it('returns bin when MIME is null and no asset type', function () {
    expect(MimeTypeMap::toExtension(null))->toBe('bin');
});

it('returns bin when MIME is unknown and no asset type', function () {
    expect(MimeTypeMap::toExtension('application/x-unknown'))->toBe('bin');
});

it('prefers MIME type over asset type fallback', function () {
    expect(MimeTypeMap::toExtension('audio/wav', AssetType::Audio))->toBe('wav');
});

// ─── fromFormat ────────────────────────────────────────────────

it('resolves MIME type from known format', function (string $format, string $expected) {
    expect(MimeTypeMap::fromFormat($format))->toBe($expected);
})->with([
    ['png', 'image/png'],
    ['jpg', 'image/jpeg'],
    ['jpeg', 'image/jpeg'],
    ['webp', 'image/webp'],
    ['gif', 'image/gif'],
    ['mp3', 'audio/mpeg'],
    ['wav', 'audio/wav'],
    ['ogg', 'audio/ogg'],
    ['mp4', 'video/mp4'],
    ['webm', 'video/webm'],
]);

it('returns null for unknown format', function () {
    expect(MimeTypeMap::fromFormat('bmp'))->toBeNull();
});

it('returns null when format is null', function () {
    expect(MimeTypeMap::fromFormat(null))->toBeNull();
});

// ─── defaultMimeType ───────────────────────────────────────────

it('returns default MIME type for media asset types', function (AssetType $assetType, ?string $expected) {
    expect(MimeTypeMap::defaultMimeType($assetType))->toBe($expected);
})->with([
    [AssetType::Image, 'image/png'],
    [AssetType::Audio, 'audio/mpeg'],
    [AssetType::Video, 'video/mp4'],
    [AssetType::Document, null],
    [AssetType::Text, null],
    [AssetType::Json, null],
    [AssetType::File, null],
]);
