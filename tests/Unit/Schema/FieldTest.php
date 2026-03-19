<?php

declare(strict_types=1);

use Atlasphp\Atlas\Schema\Fields\BooleanField;
use Atlasphp\Atlas\Schema\Fields\EnumField;
use Atlasphp\Atlas\Schema\Fields\IntegerField;
use Atlasphp\Atlas\Schema\Fields\NumberField;
use Atlasphp\Atlas\Schema\Fields\StringField;

it('produces string schema', function () {
    expect(new StringField('name', 'The name'))
        ->toSchema()->toBe(['type' => 'string', 'description' => 'The name']);
});

it('produces integer schema', function () {
    expect(new IntegerField('count', 'The count'))
        ->toSchema()->toBe(['type' => 'integer', 'description' => 'The count']);
});

it('produces number schema', function () {
    expect(new NumberField('amount', 'The amount'))
        ->toSchema()->toBe(['type' => 'number', 'description' => 'The amount']);
});

it('produces boolean schema', function () {
    expect(new BooleanField('active', 'Is active'))
        ->toSchema()->toBe(['type' => 'boolean', 'description' => 'Is active']);
});

it('produces enum schema', function () {
    expect(new EnumField('status', 'The status', ['a', 'b']))
        ->toSchema()->toBe(['type' => 'string', 'description' => 'The status', 'enum' => ['a', 'b']]);
});

it('is required by default', function () {
    expect(new StringField('x', 'y'))->isRequired()->toBeTrue();
    expect(new IntegerField('x', 'y'))->isRequired()->toBeTrue();
    expect(new NumberField('x', 'y'))->isRequired()->toBeTrue();
    expect(new BooleanField('x', 'y'))->isRequired()->toBeTrue();
    expect(new EnumField('x', 'y', ['a']))->isRequired()->toBeTrue();
});

it('optional returns a new instance', function () {
    $field = new StringField('x', 'y');
    $optional = $field->optional();

    expect($field->isRequired())->toBeTrue();
    expect($optional->isRequired())->toBeFalse();
    expect($field)->not->toBe($optional);
});

it('exposes name and description accessors', function () {
    $field = new StringField('foo', 'bar');

    expect($field->name())->toBe('foo');
    expect($field->description())->toBe('bar');
});

it('rejects empty enum options', function () {
    new EnumField('status', 'The status', []);
})->throws(InvalidArgumentException::class, 'EnumField requires at least one option.');
