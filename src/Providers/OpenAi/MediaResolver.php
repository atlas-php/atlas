<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi;

use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Contracts\MediaResolver as MediaResolverContract;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Converts Atlas Input types into OpenAI Responses API content parts.
 *
 * Maps media inputs to `input_image` or `input_file` content part format.
 */
class MediaResolver implements MediaResolverContract
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(Input $input): array
    {
        if ($input->isUrl()) {
            return [
                'type' => 'input_image',
                'image_url' => $input->url(),
            ];
        }

        if ($input->isBase64()) {
            return [
                'type' => 'input_image',
                'image_url' => "data:{$input->mimeType()};base64,{$input->data()}",
            ];
        }

        if ($input->isPath()) {
            $raw = file_get_contents($input->path());

            if ($raw === false) {
                throw new InvalidArgumentException("Cannot read media file: {$input->path()}");
            }

            $data = base64_encode($raw);

            return [
                'type' => 'input_image',
                'image_url' => "data:{$input->mimeType()};base64,{$data}",
            ];
        }

        if ($input->isFileId()) {
            return [
                'type' => 'input_file',
                'file_id' => $input->fileId(),
            ];
        }

        if ($input->disk() !== null) {
            $raw = Storage::disk($input->disk())->get($input->path());

            if ($raw === null) {
                throw new InvalidArgumentException("Cannot read media file from storage: {$input->path()}");
            }

            $data = base64_encode($raw);

            return [
                'type' => 'input_image',
                'image_url' => "data:{$input->mimeType()};base64,{$data}",
            ];
        }

        throw new InvalidArgumentException('Cannot resolve media input — no source set.');
    }
}
