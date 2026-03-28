<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware\Contracts;

/**
 * Provider middleware scoped to video modality calls (video, videoToText).
 *
 * Implement this interface to run middleware only on video-related provider calls.
 * Receives ProviderContext with method 'video' or 'videoToText'.
 */
interface VideoMiddleware extends ProviderMiddleware {}
