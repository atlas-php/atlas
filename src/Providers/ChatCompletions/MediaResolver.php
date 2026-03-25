<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ChatCompletions;

use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Concerns\ResolvesMediaUri;
use Atlasphp\Atlas\Providers\Contracts\MediaResolverContract;

/**
 * Converts Atlas Input types into Chat Completions image_url content parts.
 *
 * Uses the nested `{"type": "image_url", "image_url": {"url": "..."}}` format.
 */
class MediaResolver implements MediaResolverContract
{
    use ResolvesMediaUri;

    /**
     * @return array<string, mixed>
     */
    public function resolve(Input $input): array
    {
        return [
            'type' => 'image_url',
            'image_url' => ['url' => $this->resolveToUri($input)],
        ];
    }
}
