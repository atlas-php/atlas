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
    public function generate(AudioRequest $request): AudioResponse;

    public function transcribe(AudioRequest $request): TextResponse;
}
