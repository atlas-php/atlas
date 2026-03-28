<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware\Contracts;

/**
 * Marker interface for Laravel HTTP middleware applied to voice webhook routes.
 *
 * This is standard Laravel HTTP middleware (receives Request, returns Response),
 * applied to the voice tool, transcript, and close endpoints.
 */
interface VoiceHttpMiddleware {}
