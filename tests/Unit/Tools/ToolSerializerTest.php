<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tools\ToolSerializer;

it('serializes strings as-is', function () {
    expect(ToolSerializer::serialize('hello'))->toBe('hello');
});

it('serializes arrays to json', function () {
    expect(ToolSerializer::serialize(['a' => 1]))->toBe('{"a":1}');
});

it('serializes integers to string', function () {
    expect(ToolSerializer::serialize(42))->toBe('42');
});

it('serializes floats to string', function () {
    expect(ToolSerializer::serialize(3.14))->toBe('3.14');
});

it('serializes true to string', function () {
    expect(ToolSerializer::serialize(true))->toBe('true');
});

it('serializes false to string', function () {
    expect(ToolSerializer::serialize(false))->toBe('false');
});

it('serializes null to fallback message', function () {
    expect(ToolSerializer::serialize(null))->toBe('No result returned.');
});

it('serializes JsonSerializable to json', function () {
    $obj = new class implements JsonSerializable
    {
        public function jsonSerialize(): mixed
        {
            return ['key' => 'value'];
        }
    };

    expect(ToolSerializer::serialize($obj))->toBe('{"key":"value"}');
});

it('serializes object with toArray to json', function () {
    $obj = new class
    {
        /** @return array<string, int> */
        public function toArray(): array
        {
            return ['x' => 1, 'y' => 2];
        }
    };

    expect(ToolSerializer::serialize($obj))->toBe('{"x":1,"y":2}');
});

it('serializes object with toJson using its output', function () {
    $obj = new class
    {
        public function toJson(): string
        {
            return '{"custom":true}';
        }
    };

    expect(ToolSerializer::serialize($obj))->toBe('{"custom":true}');
});

it('serializes plain object by casting to array', function () {
    $obj = new stdClass;
    $obj->foo = 'bar';

    expect(ToolSerializer::serialize($obj))->toBe('{"foo":"bar"}');
});

it('prefers toArray over toJson when both exist', function () {
    $obj = new class
    {
        /** @return array<string, string> */
        public function toArray(): array
        {
            return ['from' => 'toArray'];
        }

        public function toJson(): string
        {
            return '{"from":"toJson"}';
        }
    };

    expect(ToolSerializer::serialize($obj))->toBe('{"from":"toArray"}');
});
