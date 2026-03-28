<?php

declare(strict_types=1);

use Atlasphp\Atlas\Schema\Fields\ArrayField;
use Atlasphp\Atlas\Schema\Fields\BooleanField;
use Atlasphp\Atlas\Schema\Fields\EnumField;
use Atlasphp\Atlas\Schema\Fields\IntegerField;
use Atlasphp\Atlas\Schema\Fields\NumberField;
use Atlasphp\Atlas\Schema\Fields\ObjectField;
use Atlasphp\Atlas\Schema\Fields\StringField;
use Atlasphp\Atlas\Schema\SchemaBuilder;

it('creates correct field types', function () {
    expect(SchemaBuilder::string('x', 'y'))->toBeInstanceOf(StringField::class);
    expect(SchemaBuilder::integer('x', 'y'))->toBeInstanceOf(IntegerField::class);
    expect(SchemaBuilder::number('x', 'y'))->toBeInstanceOf(NumberField::class);
    expect(SchemaBuilder::boolean('x', 'y'))->toBeInstanceOf(BooleanField::class);
    expect(SchemaBuilder::enum('x', 'y', ['a']))->toBeInstanceOf(EnumField::class);
    expect(SchemaBuilder::stringArray('x', 'y'))->toBeInstanceOf(ArrayField::class);
    expect(SchemaBuilder::numberArray('x', 'y'))->toBeInstanceOf(ArrayField::class);
    expect(SchemaBuilder::array('x', 'y', fn ($s) => $s))->toBeInstanceOf(ArrayField::class);
    expect(SchemaBuilder::object('x', 'y'))->toBeInstanceOf(ObjectField::class);
});
