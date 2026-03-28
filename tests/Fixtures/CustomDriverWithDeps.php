<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\AtlasCache;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\ProviderConfig;

/**
 * Test fixture for verifying custom driver constructor dependency injection.
 */
class CustomDriverWithDeps extends Driver
{
    public function __construct(
        ProviderConfig $config,
        HttpClient $http,
        ?MiddlewareStack $middlewareStack = null,
        ?AtlasCache $cache = null,
    ) {
        parent::__construct($config, $http, $middlewareStack, $cache);
    }

    public function name(): string
    {
        return 'custom-with-deps';
    }

    public function capabilities(): ProviderCapabilities
    {
        return ProviderCapabilities::withOverrides(
            new ProviderCapabilities(text: true),
            $this->config->capabilityOverrides,
        );
    }

    public function hasHttp(): bool
    {
        return $this->http instanceof HttpClient;
    }

    public function hasMiddlewareStack(): bool
    {
        return $this->middlewareStack instanceof MiddlewareStack;
    }

    public function hasCache(): bool
    {
        return $this->cache instanceof AtlasCache;
    }
}
