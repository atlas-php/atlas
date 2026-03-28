<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google;

use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Contracts\MediaResolverContract;
use InvalidArgumentException;

/**
 * Converts Input types into Gemini's inline_data or file_data part format.
 */
class MediaResolver implements MediaResolverContract
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(Input $input): array
    {
        if ($input->isUrl()) {
            $url = (string) $input->url();

            if (str_starts_with($url, 'gs://')) {
                return [
                    'file_data' => [
                        'mime_type' => $input->mimeType(),
                        'file_uri' => $url,
                    ],
                ];
            }

            $raw = @file_get_contents($url);

            if ($raw === false) {
                throw new InvalidArgumentException('Failed to read media from URL: '.$url);
            }

            return [
                'inline_data' => [
                    'mime_type' => $input->mimeType(),
                    'data' => base64_encode($raw),
                ],
            ];
        }

        if ($input->isBase64()) {
            return [
                'inline_data' => [
                    'mime_type' => $input->mimeType(),
                    'data' => $input->data(),
                ],
            ];
        }

        if ($input->isPath()) {
            $raw = @file_get_contents((string) $input->path());

            if ($raw === false) {
                throw new InvalidArgumentException('Failed to read media from path: '.$input->path());
            }

            return [
                'inline_data' => [
                    'mime_type' => $input->mimeType(),
                    'data' => base64_encode($raw),
                ],
            ];
        }

        if ($input->isStorage()) {
            return [
                'inline_data' => [
                    'mime_type' => $input->mimeType(),
                    'data' => base64_encode($input->contents()),
                ],
            ];
        }

        if ($input->isUpload()) {
            return [
                'inline_data' => [
                    'mime_type' => $input->mimeType(),
                    'data' => $input->toBase64(),
                ],
            ];
        }

        if ($input->isFileId()) {
            return [
                'file_data' => [
                    'mime_type' => $input->mimeType(),
                    'file_uri' => $input->fileId(),
                ],
            ];
        }

        throw new InvalidArgumentException('Cannot resolve media input — no supported source set.');
    }
}
