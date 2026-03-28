<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema\Fields;

/**
 * A boolean field that produces a JSON Schema boolean type.
 */
class BooleanField extends Field
{
    /**
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return [
            'type' => 'boolean',
            'description' => $this->description,
        ];
    }
}
