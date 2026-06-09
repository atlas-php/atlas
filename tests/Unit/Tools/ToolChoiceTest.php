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
