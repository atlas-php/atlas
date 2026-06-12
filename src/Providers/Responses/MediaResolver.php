<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Responses;

use Atlasphp\Atlas\Input\Document;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Concerns\ResolvesMediaUri;
use Atlasphp\Atlas\Providers\Contracts\MediaResolverContract;

/**
 * Converts Atlas Input types into OpenAI Responses API content parts.
 *
 * Shared by every provider that speaks the Responses API wire format
 * (OpenAI, xAI, and generic Responses-compatible proxies). Maps document
 * inputs to `input_file` parts and other media to `input_image`.
 */
class MediaResolver implements MediaResolverContract
{
    use ResolvesMediaUri;

    /**
     * @return array<string, mixed>
     */
    public function resolve(Input $input): array
    {
        if ($input->isFileId()) {
            return [
                'type' => 'input_file',
                'file_id' => $input->fileId(),
            ];
        }

        if ($input instanceof Document) {
            return $this->resolveDocument($input);
        }

        return [
            'type' => 'input_image',
            'image_url' => $this->resolveToUri($input),
        ];
    }

    /**
     * Resolve a document into an OpenAI `input_file` content part.
     *
     * URLs are passed through as `file_url` (OpenAI fetches them); every other
     * source is inlined as a base64 `file_data` data URI with a filename, which
     * OpenAI requires to infer the file type.
     *
     * @return array<string, mixed>
     */
    private function resolveDocument(Document $input): array
    {
        if ($input->isUrl()) {
            return [
                'type' => 'input_file',
                'file_url' => (string) $input->url(),
            ];
        }

        return [
            'type' => 'input_file',
            'filename' => $this->documentFilename($input->mimeType()),
            'file_data' => $this->resolveToUri($input),
        ];
    }

    /**
     * Derive a filename from the document's MIME type. OpenAI requires a
     * filename alongside `file_data` and uses its extension to decide how to
     * parse the file, so the extension must reflect the actual format. The map
     * covers the document types OpenAI accepts inline; unrecognized types fall
     * back to the MIME subtype (e.g. `application/foo` -> `foo`) and finally
     * `bin`, rather than mislabeling everything as a PDF.
     */
    private function documentFilename(string $mimeType): string
    {
        $extension = match ($mimeType) {
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/markdown' => 'md',
            'text/html' => 'html',
            'text/xml', 'application/xml' => 'xml',
            'text/csv' => 'csv',
            'text/tab-separated-values' => 'tsv',
            'application/json' => 'json',
            'application/rtf', 'text/rtf' => 'rtf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.oasis.opendocument.text' => 'odt',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            default => $this->extensionFromSubtype($mimeType),
        };

        return "document.{$extension}";
    }

    /**
     * Best-effort extension for an unmapped MIME type: use the subtype when it
     * looks like a plain extension, otherwise `bin`.
     */
    private function extensionFromSubtype(string $mimeType): string
    {
        $subtype = substr((string) strrchr($mimeType, '/'), 1);

        return $subtype !== '' && preg_match('/^[a-z0-9]+$/i', $subtype) === 1 ? $subtype : 'bin';
    }
}
