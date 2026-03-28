<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware\Contracts;

/**
 * Provider middleware scoped to audio modality calls (audio, audioToText).
 *
 * Implement this interface to run middleware only on audio-related provider calls.
 * Receives ProviderContext with method 'audio' or 'audioToText'.
 */
interface AudioMiddleware extends ProviderMiddleware {}
