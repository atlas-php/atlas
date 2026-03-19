<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Providers\ModelList;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * Handler for provider-level interrogation endpoints.
 *
 * Manages metadata queries like listing available models and voices.
 * Providers implement this for their specific API endpoints.
 */
interface ProviderHandler
{
    public function models(): ModelList;

    public function voices(): VoiceList;

    public function validate(): bool;
}
