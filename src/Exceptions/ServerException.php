<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

/**
 * Thrown on a provider-side server error (HTTP 5xx). Retryable as a transient
 * failure when retries are enabled.
 */
class ServerException extends ProviderException {}
