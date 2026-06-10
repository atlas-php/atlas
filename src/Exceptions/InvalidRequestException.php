<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

/**
 * Thrown when the provider rejects the request as malformed or invalid (HTTP 400).
 */
class InvalidRequestException extends ProviderException {}
