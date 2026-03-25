<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

/**
 * Marks a response type that produces storable binary content.
 */
interface StorableContract
{
    public function contents(): string;
}
