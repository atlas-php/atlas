<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\HasToolChoiceSupport;
use Atlasphp\Atlas\Tools\Enums\ToolChoice;

class TestClassWithToolChoice
{
    use HasToolChoiceSupport;

    public function exposedGetToolChoice(): ToolChoice|string|null
    {
        return $this->getToolChoice();
    }
}

test('withToolChoice sets enum value', function () {
    $instance = new TestClassWithToolChoice;
    $modified = $instance->withToolChoice(ToolChoice::Any);

    expect($modified->exposedGetToolChoice())->toBe(ToolChoice::Any);
});

test('withToolChoice sets string value', function () {
    $instance = new TestClassWithToolChoice;
    $modified = $instance->withToolChoice('calculator');

    expect($modified->exposedGetToolChoice())->toBe('calculator');
});

test('withToolChoice returns clone', function () {
    $instance = new TestClassWithToolChoice;
    $modified = $instance->withToolChoice(ToolChoice::None);

    expect($modified)->not->toBe($instance);
    expect($instance->exposedGetToolChoice())->toBeNull();
});

test('requireTool sets Any choice', function () {
    $instance = new TestClassWithToolChoice;
    $modified = $instance->requireTool();

    expect($modified->exposedGetToolChoice())->toBe(ToolChoice::Any);
});

test('disableTools sets None choice', function () {
    $instance = new TestClassWithToolChoice;
    $modified = $instance->disableTools();

    expect($modified->exposedGetToolChoice())->toBe(ToolChoice::None);
});

test('forceTool sets specific tool name', function () {
    $instance = new TestClassWithToolChoice;
    $modified = $instance->forceTool('search');

    expect($modified->exposedGetToolChoice())->toBe('search');
});

test('getToolChoice returns null by default', function () {
    $instance = new TestClassWithToolChoice;

    expect($instance->exposedGetToolChoice())->toBeNull();
});
