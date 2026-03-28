<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Concerns;

use Atlasphp\Atlas\Input\Input;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Resolves an Input to a URI string (URL or data URI).
 *
 * Shared across MediaResolver implementations — each provider
 * wraps the resolved URI in its own format.
 */
trait ResolvesMediaUri
{
    /**
     * Resolve an Input to a URL or base64 data URI string.
     */
    protected function resolveToUri(Input $input): string
    {
        if ($input->isUrl()) {
            return $input->url();
        }

        if ($input->isBase64()) {
            return "data:{$input->mimeType()};base64,{$input->data()}";
        }

        if ($input->isStorage()) {
            $raw = Storage::disk($input->storageDisk())->get($input->storagePath());

            if ($raw === null) {
                throw new InvalidArgumentException("Cannot read media file from storage: {$input->storagePath()}");
            }

            return "data:{$input->mimeType()};base64,".base64_encode($raw);
        }

        if ($input->isPath()) {
            $raw = file_get_contents($input->path());

            if ($raw === false) {
                throw new InvalidArgumentException("Cannot read media file: {$input->path()}");
            }

            return "data:{$input->mimeType()};base64,".base64_encode($raw);
        }

        if ($input->isUpload()) {
            return "data:{$input->mimeType()};base64,{$input->toBase64()}";
        }

        throw new InvalidArgumentException('Cannot resolve media input — no source set.');
    }
}
