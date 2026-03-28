<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema\Fields;

/**
 * An integer field that produces a JSON Schema integer type.
 */
class IntegerField extends Field
{
    /**
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return [
            'type' => 'integer',
            'description' => $this->description,
        ];
    }
}
