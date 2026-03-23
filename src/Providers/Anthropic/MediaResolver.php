<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Anthropic;

use Atlasphp\Atlas\Input\Document;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Contracts\MediaResolver as MediaResolverContract;
use InvalidArgumentException;

/**
 * Converts Input types into Anthropic's image or document content block format.
 */
class MediaResolver implements MediaResolverContract
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(Input $input): array
    {
        $isDocument = $input instanceof Document;

        if ($input->isUrl() && ! $isDocument) {
            return [
                'type' => 'image',
                'source' => [
                    'type' => 'url',
                    'url' => (string) $input->url(),
                ],
            ];
        }

        if ($input->isBase64()) {
            return $this->buildBase64Block($input, $isDocument);
        }

        if ($input->isUrl()) {
            $raw = @file_get_contents((string) $input->url());

            if ($raw === false) {
                throw new InvalidArgumentException('Failed to read media from URL: '.$input->url());
            }

            return $this->buildBase64BlockFromData(base64_encode($raw), $input->mimeType(), $isDocument);
        }

        if ($input->isPath()) {
            $raw = @file_get_contents((string) $input->path());

            if ($raw === false) {
                throw new InvalidArgumentException('Failed to read media from path: '.$input->path());
            }

            return $this->buildBase64BlockFromData(base64_encode($raw), $input->mimeType(), $isDocument);
        }

        if ($input->isStorage()) {
            return $this->buildBase64BlockFromData(base64_encode($input->contents()), $input->mimeType(), $isDocument);
        }

        if ($input->isUpload()) {
            return $this->buildBase64BlockFromData($input->toBase64(), $input->mimeType(), $isDocument);
        }

        throw new InvalidArgumentException('Cannot resolve media input — no supported source set.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBase64Block(Input $input, bool $isDocument): array
    {
        return $this->buildBase64BlockFromData((string) $input->data(), $input->mimeType(), $isDocument);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBase64BlockFromData(string $data, string $mimeType, bool $isDocument): array
    {
        if ($isDocument) {
            return [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $mimeType,
                    'data' => $data,
                ],
            ];
        }

        return [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => $mimeType,
                'data' => $data,
            ],
        ];
    }
}
