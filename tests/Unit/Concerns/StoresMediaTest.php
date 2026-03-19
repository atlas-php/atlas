<?php

declare(strict_types=1);

use Atlasphp\Atlas\Concerns\StoresMedia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// Concrete test class using the trait
function makeMediaStub(string $type, string $value, ?string $disk = null): object
{
    return new class($type, $value, $disk)
    {
        use StoresMedia;

        public function __construct(
            private readonly string $type,
            private readonly string $value,
            private readonly ?string $disk,
        ) {}

        protected function mediaSource(): array
        {
            return ['type' => $this->type, 'value' => $this->value, 'disk' => $this->disk];
        }

        protected function defaultExtension(): string
        {
            return 'png';
        }
    };
}

it('stores to default disk with auto-generated path', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $stub = makeMediaStub('base64', base64_encode('image-data'));
    $path = $stub->store();

    expect($path)->toStartWith('atlas/');
    expect($path)->toEndWith('.png');
    Storage::disk('local')->assertExists($path);
});

it('stores at exact path with storeAs', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $stub = makeMediaStub('base64', base64_encode('image-data'));
    $path = $stub->storeAs('custom/photo.png');

    expect($path)->toBe('custom/photo.png');
    Storage::disk('local')->assertExists('custom/photo.png');
});

it('stores to specified disk', function () {
    Storage::fake('s3');

    $stub = makeMediaStub('base64', base64_encode('image-data'));
    $path = $stub->store('s3');

    Storage::disk('s3')->assertExists($path);
});

it('stores publicly with storePublicly', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $stub = makeMediaStub('base64', base64_encode('image-data'));
    $path = $stub->storePublicly();

    Storage::disk('local')->assertExists($path);
});

it('stores publicly at exact path with storePubliclyAs', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $stub = makeMediaStub('base64', base64_encode('data'));
    $path = $stub->storePubliclyAs('public/photo.png');

    expect($path)->toBe('public/photo.png');
    Storage::disk('local')->assertExists('public/photo.png');
});

it('resolves contents from base64', function () {
    $stub = makeMediaStub('base64', base64_encode('hello'));

    expect($stub->contents())->toBe('hello');
});

it('resolves contents from file path', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'atlas_');
    file_put_contents($tmp, 'file-content');

    $stub = makeMediaStub('path', $tmp);

    expect($stub->contents())->toBe('file-content');

    unlink($tmp);
});

it('resolves contents from storage', function () {
    Storage::fake('local');
    Storage::disk('local')->put('test/file.txt', 'storage-content');

    $stub = makeMediaStub('storage', 'test/file.txt', 'local');

    expect($stub->contents())->toBe('storage-content');
});

it('resolves contents from url', function () {
    Http::fake([
        'example.com/image.png' => Http::response('url-content'),
    ]);

    $stub = makeMediaStub('url', 'https://example.com/image.png');

    expect($stub->contents())->toBe('url-content');
});

it('resolves contents from raw', function () {
    $stub = makeMediaStub('raw', 'raw-binary-data');

    expect($stub->contents())->toBe('raw-binary-data');
});

it('returns base64 encoded contents via toBase64', function () {
    $stub = makeMediaStub('raw', 'hello');

    expect($stub->toBase64())->toBe(base64_encode('hello'));
});

it('uses atlas.storage.disk config', function () {
    Storage::fake('custom-disk');
    config()->set('atlas.storage.disk', 'custom-disk');

    $stub = makeMediaStub('base64', base64_encode('data'));
    $path = $stub->store();

    Storage::disk('custom-disk')->assertExists($path);
});

it('uses atlas.storage.prefix config in auto paths', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
    config()->set('atlas.storage.prefix', 'my-app');

    $stub = makeMediaStub('base64', base64_encode('data'));
    $path = $stub->store();

    expect($path)->toStartWith('my-app/');
});
