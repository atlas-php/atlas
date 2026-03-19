<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema\Fields;

/**
 * A string field that produces a JSON Schema string type.
 */
class StringField extends Field
{
    /**
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return [
            'type' => 'string',
            'description' => $this->description,
        ];
    }
}
