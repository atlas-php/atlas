<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * Handler for image generation and description.
 */
interface ImageHandler
{
    public function image(ImageRequest $request): ImageResponse;

    public function imageToText(ImageRequest $request): TextResponse;
}
