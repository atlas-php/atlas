<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tools\Support\PrismParameterConverter;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Prism\Prism\Tool as PrismTool;

afterEach(function () {
    Mockery::close();
});

test('it adds string parameter to tool', function () {
    $tool = Mockery::mock(PrismTool::class);
    $tool->shouldReceive('withStringParameter')
        ->once()
        ->with('name', 'The name', true)
        ->andReturnSelf();

    $param = ToolParameter::string('name', 'The name', required: true);

    PrismParameterConverter::addParameter($tool, $param);
});

test('it adds optional string parameter to tool', function () {
    $tool = Mockery::mock(PrismTool::class);
    $tool->shouldReceive('withStringParameter')
        ->once()
        ->with('nickname', 'Optional nickname', false)
        ->andReturnSelf();

    $param = ToolParameter::string('nickname', 'Optional nickname', required: false);

    PrismParameterConverter::addParameter($tool, $param);
});

test('it adds integer parameter as number to tool', function () {
    $tool = Mockery::mock(PrismTool::class);
    $tool->shouldReceive('withNumberParameter')
        ->once()
        ->with('count', 'The count', true)
        ->andReturnSelf();

    $param = ToolParameter::integer('count', 'The count', required: true);

    PrismParameterConverter::addParameter($tool, $param);
});

test('it adds number parameter to tool', function () {
    $tool = Mockery::mock(PrismTool::class);
    $tool->shouldReceive('withNumberParameter')
        ->once()
        ->with('price', 'The price', false)
        ->andReturnSelf();

    $param = ToolParameter::number('price', 'The price', required: false);

    PrismParameterConverter::addParameter($tool, $param);
});

test('it adds boolean parameter to tool', function () {
    $tool = Mockery::mock(PrismTool::class);
    $tool->shouldReceive('withBooleanParameter')
        ->once()
        ->with('active', 'Is active', true)
        ->andReturnSelf();

    $param = ToolParameter::boolean('active', 'Is active', required: true);

    PrismParameterConverter::addParameter($tool, $param);
});

test('it adds enum parameter to tool', function () {
    $tool = Mockery::mock(PrismTool::class);
    $tool->shouldReceive('withEnumParameter')
        ->once()
        ->with('status', 'The status', ['pending', 'active', 'completed'], true)
        ->andReturnSelf();

    $param = ToolParameter::enum('status', 'The status', ['pending', 'active', 'completed'], required: true);

    PrismParameterConverter::addParameter($tool, $param);
});

test('it adds optional enum parameter to tool', function () {
    $tool = Mockery::mock(PrismTool::class);
    $tool->shouldReceive('withEnumParameter')
        ->once()
        ->with('priority', 'The priority', ['low', 'medium', 'high'], false)
        ->andReturnSelf();

    $param = ToolParameter::enum('priority', 'The priority', ['low', 'medium', 'high'], required: false);

    PrismParameterConverter::addParameter($tool, $param);
});

test('it adds array parameter using withParameter', function () {
    $tool = Mockery::mock(PrismTool::class);
    $tool->shouldReceive('withParameter')
        ->once()
        ->with(Mockery::type(\Prism\Prism\Schema\ArraySchema::class), true)
        ->andReturnSelf();

    $param = ToolParameter::array('items', 'The items', required: true);

    PrismParameterConverter::addParameter($tool, $param);
});

test('it adds object parameter using withParameter', function () {
    $tool = Mockery::mock(PrismTool::class);
    $tool->shouldReceive('withParameter')
        ->once()
        ->with(Mockery::type(\Prism\Prism\Schema\ObjectSchema::class), true)
        ->andReturnSelf();

    $param = ToolParameter::object('config', 'The configuration', [
        ToolParameter::string('key', 'The key'),
    ], required: true);

    PrismParameterConverter::addParameter($tool, $param);
});

test('it falls back to string parameter for unknown type', function () {
    $tool = Mockery::mock(PrismTool::class);
    $tool->shouldReceive('withStringParameter')
        ->once()
        ->with('custom', 'A custom parameter', true)
        ->andReturnSelf();

    $param = new ToolParameter(
        name: 'custom',
        type: 'unknown_type',
        description: 'A custom parameter',
        required: true,
    );

    PrismParameterConverter::addParameter($tool, $param);
});

test('enum parameter takes precedence over type', function () {
    $tool = Mockery::mock(PrismTool::class);
    // Should call withEnumParameter, not withStringParameter
    $tool->shouldReceive('withEnumParameter')
        ->once()
        ->with('choice', 'A choice', ['a', 'b', 'c'], true)
        ->andReturnSelf();

    $param = new ToolParameter(
        name: 'choice',
        type: 'string',
        description: 'A choice',
        required: true,
        enum: ['a', 'b', 'c'],
    );

    PrismParameterConverter::addParameter($tool, $param);
});
