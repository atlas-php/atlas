<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;

/**
 * Handler for embeddings generation.
 */
interface EmbedHandler
{
    public function embed(EmbedRequest $request): EmbeddingsResponse;
}
