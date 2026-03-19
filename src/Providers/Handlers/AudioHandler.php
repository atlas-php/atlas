<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * Handler for audio generation and transcription.
 */
interface AudioHandler
{
    public function audio(AudioRequest $request): AudioResponse;

    public function audioToText(AudioRequest $request): TextResponse;
}
