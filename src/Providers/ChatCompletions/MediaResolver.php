<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ChatCompletions;

use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Contracts\MediaResolver as MediaResolverContract;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Converts Atlas Input types into Chat Completions image_url content parts.
 *
 * Uses the nested `{"type": "image_url", "image_url": {"url": "..."}}` format.
 */
class MediaResolver implements MediaResolverContract
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(Input $input): array
    {
        $url = match (true) {
            $input->isUrl() => $input->url(),
            $input->isBase64() => "data:{$input->mimeType()};base64,{$input->data()}",
            $input->isStorage() => $this->resolveFromStorage($input),
            $input->isPath() => $this->resolveFromPath($input),
            $input->isUpload() => "data:{$input->mimeType()};base64,{$input->toBase64()}",
            default => throw new InvalidArgumentException('Cannot resolve media input — no source set.'),
        };

        return [
            'type' => 'image_url',
            'image_url' => ['url' => $url],
        ];
    }

    private function resolveFromStorage(Input $input): string
    {
        $raw = Storage::disk($input->storageDisk())->get($input->storagePath());

        if ($raw === null) {
            throw new InvalidArgumentException("Cannot read media file from storage: {$input->storagePath()}");
        }

        return "data:{$input->mimeType()};base64,".base64_encode($raw);
    }

    private function resolveFromPath(Input $input): string
    {
        $raw = file_get_contents($input->path());

        if ($raw === false) {
            throw new InvalidArgumentException("Cannot read media file: {$input->path()}");
        }

        return "data:{$input->mimeType()};base64,".base64_encode($raw);
    }
}
