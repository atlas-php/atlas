<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Responses\RerankResponse;

/**
 * Handler for document reranking.
 */
interface RerankHandler
{
    public function rerank(RerankRequest $request): RerankResponse;
}
