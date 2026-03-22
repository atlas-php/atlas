<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

/**
 * Marks a response type that produces storable binary content.
 */
interface HasContents
{
    public function contents(): string;
}
