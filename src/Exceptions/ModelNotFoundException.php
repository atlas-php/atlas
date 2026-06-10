<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

/**
 * Thrown when the provider does not recognize the requested model (HTTP 404).
 */
class ModelNotFoundException extends ProviderException {}
