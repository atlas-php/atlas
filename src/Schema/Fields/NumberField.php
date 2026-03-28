<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema\Fields;

/**
 * A number field that produces a JSON Schema number type.
 */
class NumberField extends Field
{
    /**
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return [
            'type' => 'number',
            'description' => $this->description,
        ];
    }
}
