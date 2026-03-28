<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Concerns;

use Atlasphp\Atlas\Input\Input;
use Illuminate\Support\Facades\Http;

/**
 * Resolves an Input to raw audio file bytes.
 *
 * Supports path, base64, and URL sources. Provider-agnostic utility
 * shared across audio handlers that need binary file contents.
 */
trait ResolvesAudioFile
{
    protected function resolveAudioFile(Input $media): string
    {
        if ($media->isPath()) {
            $raw = file_get_contents($media->path());

            if ($raw === false) {
                throw new \InvalidArgumentException("Cannot read audio file: {$media->path()}");
            }

            return $raw;
        }

        if ($media->isBase64()) {
            $decoded = base64_decode($media->data(), true);

            if ($decoded === false) {
                throw new \InvalidArgumentException('Cannot decode base64 audio data: invalid encoding.');
            }

            return $decoded;
        }

        if ($media->isUrl()) {
            return Http::timeout(30)->get($media->url())->throw()->body();
        }

        throw new \InvalidArgumentException('Cannot resolve audio input — no supported source set.');
    }
}
