<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Contracts;

use Prism\Prism\Tool as PrismTool;

/**
 * Optional interface for tools that want to configure the Prism Tool directly.
 *
 * Implement this interface to access the full Prism Tool API:
 * - Custom error handling via `failed()`, `withErrorHandling()`, `withoutErrorHandling()`
 * - Provider-specific options via `withProviderOptions()`
 * - Any future Prism Tool features
 */
interface ConfiguresPrismTool
{
    /**
     * Configure the Prism Tool with additional options.
     *
     * @param  PrismTool  $tool  The fully-built Prism Tool.
     * @return PrismTool The configured tool (can chain methods).
     */
    public function configurePrismTool(PrismTool $tool): PrismTool;
}
