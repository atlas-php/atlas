<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Requests\VideoRequest;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\VideoResponse;

/**
 * Handler for video generation and description.
 */
interface VideoHandler
{
    public function video(VideoRequest $request): VideoResponse;

    public function videoToText(VideoRequest $request): TextResponse;
}
