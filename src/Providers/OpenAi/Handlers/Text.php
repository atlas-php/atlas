<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Providers\OpenAi\Concerns\HasOrganizationHeader;
use Atlasphp\Atlas\Providers\Responses\Handlers\Text as ResponsesText;

/**
 * OpenAI text handler using the Responses API.
 *
 * Inherits the full Responses flow (text, streaming, structured output,
 * input-token counting) and only adds the OpenAI-Organization header.
 */
class Text extends ResponsesText
{
    use HasOrganizationHeader;
}
