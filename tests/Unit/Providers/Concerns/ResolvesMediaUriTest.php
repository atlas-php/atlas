<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Concerns\ResolvesMediaUri;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// Create a concrete class that uses the trait so we can test the protected method
beforeEach(function () {
    $this->resolver = new class
    {
        use ResolvesMediaUri {
            resolveToUri as public;
        }
    };
});

// ─── Storage source ────────────────────────────────────────────────

it('resolves storage input to base64 data URI', function () {
    Storage::fake('local');
    Storage::disk('local')->put('images/test.jpg', 'fake-image-content');

    $input = Image::fromStorage('images/test.jpg', 'local');

    $uri = $this->resolver->resolveToUri($input);

    expect($uri)->toBe('data:image/jpeg;base64,'.base64_encode('fake-image-content'));
});

it('throws when storage file cannot be read', function () {
    Storage::fake('local');
    // Do NOT put any file — path doesn't exist

    $input = Image::fromStorage('nonexistent/file.jpg', 'local');

    $this->resolver->resolveToUri($input);
})->throws(InvalidArgumentException::class, 'Cannot read media file from storage: nonexistent/file.jpg');

// ─── Local path source ─────────────────────────────────────────────

it('resolves local path input to base64 data URI', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'atlas_test_');
    file_put_contents($tmpFile, 'local-file-content');

    $input = Image::fromPath($tmpFile);

    $uri = $this->resolver->resolveToUri($input);

    expect($uri)->toBe('data:image/jpeg;base64,'.base64_encode('local-file-content'));

    unlink($tmpFile);
});

it('throws when local file cannot be read', function () {
    $input = Image::fromPath('/nonexistent/path/file.jpg');

    $this->resolver->resolveToUri($input);
    // file_get_contents triggers ErrorException via Laravel's error handler
    // before the $raw === false check can trigger InvalidArgumentException
})->throws(ErrorException::class);

// ─── Upload source ─────────────────────────────────────────────────

it('resolves uploaded file to base64 data URI', function () {
    $file = UploadedFile::fake()->image('test.png', 10, 10);

    $input = Image::fromUpload($file);

    $uri = $this->resolver->resolveToUri($input);

    expect($uri)->toStartWith('data:image/png;base64,')
        ->and(strlen($uri))->toBeGreaterThan(30);
});

// ─── No source set ─────────────────────────────────────────────────

it('throws when no source is set on input', function () {
    // Create a bare Input with no source configured
    $input = new class extends Input
    {
        public function mimeType(): string
        {
            return 'image/png';
        }

        protected function defaultExtension(): string
        {
            return 'png';
        }
    };

    $this->resolver->resolveToUri($input);
})->throws(InvalidArgumentException::class, 'Cannot resolve media input — no source set.');
