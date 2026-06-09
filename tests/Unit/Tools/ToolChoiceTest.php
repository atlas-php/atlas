<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ToolChoiceMode;
use Atlasphp\Atlas\Tools\ToolChoice;

it('builds an auto choice', function () {
    $choice = ToolChoice::auto();

    expect($choice->mode)->toBe(ToolChoiceMode::Auto)
        ->and($choice->tool)->toBeNull()
        ->and($choice->isSpecificTool())->toBeFalse();
});

it('builds a required choice', function () {
    $choice = ToolChoice::required();

    expect($choice->mode)->toBe(ToolChoiceMode::Required)
        ->and($choice->tool)->toBeNull()
        ->and($choice->isSpecificTool())->toBeFalse();
});

it('builds a none choice', function () {
    $choice = ToolChoice::none();

    expect($choice->mode)->toBe(ToolChoiceMode::None)
        ->and($choice->tool)->toBeNull()
        ->and($choice->isSpecificTool())->toBeFalse();
});

it('builds a specific-tool choice (required mode + name)', function () {
    $choice = ToolChoice::tool('get_weather');

    expect($choice->mode)->toBe(ToolChoiceMode::Required)
        ->and($choice->tool)->toBe('get_weather')
        ->and($choice->isSpecificTool())->toBeTrue();
});

it('serializes to a primitive array and back', function (ToolChoice $choice) {
    $restored = ToolChoice::fromArray($choice->toArray());

    expect($restored->mode)->toBe($choice->mode)
        ->and($restored->tool)->toBe($choice->tool);
})->with([
    'auto' => [ToolChoice::auto()],
    'required' => [ToolChoice::required()],
    'none' => [ToolChoice::none()],
    'specific tool' => [ToolChoice::tool('get_weather')],
]);

it('toArray uses the enum value and the tool name', function () {
    expect(ToolChoice::tool('get_weather')->toArray())->toBe(['mode' => 'required', 'tool' => 'get_weather'])
        ->and(ToolChoice::none()->toArray())->toBe(['mode' => 'none', 'tool' => null]);
});
