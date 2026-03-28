<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware\Contracts;

/**
 * Provider middleware scoped to voice session creation calls.
 *
 * Implement this interface to run middleware only on voice provider calls.
 * Receives ProviderContext with method 'voice'.
 */
interface VoiceMiddleware extends ProviderMiddleware {}
