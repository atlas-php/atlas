<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware\Contracts;

/**
 * Provider middleware scoped to image modality calls (image, imageToText).
 *
 * Implement this interface to run middleware only on image-related provider calls.
 * Receives ProviderContext with method 'image' or 'imageToText'.
 */
interface ImageMiddleware extends ProviderMiddleware {}
