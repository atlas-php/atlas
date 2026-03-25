<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi;

use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Concerns\ResolvesMediaUri;
use Atlasphp\Atlas\Providers\Contracts\MediaResolverContract;

/**
 * Converts Atlas Input types into OpenAI Responses API content parts.
 *
 * Maps media inputs to `input_image` or `input_file` content part format.
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

        return [
            'type' => 'input_image',
            'image_url' => $this->resolveToUri($input),
        ];
    }
}
