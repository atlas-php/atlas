<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

/**
 * Thrown when a provider does not support a requested feature.
 */
class UnsupportedFeatureException extends AtlasException
{
    /**
     * Create an exception for an unsupported feature on a given provider.
     */
    public static function make(string $feature, string $provider): self
    {
        return new self("Feature [{$feature}] is not supported by provider [{$provider}].");
    }

    /**
     * Create an exception for a provider-native tool the provider does not support.
     */
    public static function providerTool(string $type, string $provider): self
    {
        return new self("Provider tool [{$type}] is not supported by provider [{$provider}].");
    }
}
