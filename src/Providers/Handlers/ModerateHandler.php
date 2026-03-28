<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Responses\ModerationResponse;

/**
 * Handler for content moderation.
 */
interface ModerateHandler
{
    public function moderate(ModerateRequest $request): ModerationResponse;
}
