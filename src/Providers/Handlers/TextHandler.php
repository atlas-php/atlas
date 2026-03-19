<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * Handler for text generation, streaming, and structured output.
 */
interface TextHandler
{
    public function generate(TextRequest $request): TextResponse;

    public function stream(TextRequest $request): StreamResponse;

    public function structured(TextRequest $request): StructuredResponse;
}
