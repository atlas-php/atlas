<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Schema\Fields;

use InvalidArgumentException;

/**
 * An enum field that produces a JSON Schema string type with allowed values.
 */
class EnumField extends Field
{
    /**
     * @param  array<int, string>  $options
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $name,
        string $description,
        protected readonly array $options,
    ) {
        if ($options === []) {
            throw new InvalidArgumentException('EnumField requires at least one option.');
        }

        parent::__construct($name, $description);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return [
            'type' => 'string',
            'description' => $this->description,
            'enum' => $this->options,
        ];
    }
}
