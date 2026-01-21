<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tools\Exceptions\ToolException;
use Atlasphp\Atlas\Tools\Exceptions\ToolNotFoundException;

test('it creates exception for tool not found by name', function () {
    $exception = ToolNotFoundException::forName('calculator');

    expect($exception)->toBeInstanceOf(ToolNotFoundException::class);
    expect($exception->getMessage())->toBe("No tool found with name 'calculator'.");
});

test('it creates exception for tool not found by class', function () {
    $exception = ToolNotFoundException::forClass('App\\Tools\\NonExistentTool');

    expect($exception)->toBeInstanceOf(ToolNotFoundException::class);
    expect($exception->getMessage())->toBe("Tool class 'App\\Tools\\NonExistentTool' not found or could not be instantiated.");
});

test('it extends ToolException', function () {
    $exception = ToolNotFoundException::forName('test');

    expect($exception)->toBeInstanceOf(ToolException::class);
});

test('it extends base Exception class', function () {
    $exception = ToolNotFoundException::forName('test');

    expect($exception)->toBeInstanceOf(Exception::class);
});

test('it can be caught as ToolException', function () {
    $caughtAsToolException = false;

    try {
        throw ToolNotFoundException::forName('missing_tool');
    } catch (ToolException $e) {
        $caughtAsToolException = true;
        expect($e->getMessage())->toContain('missing_tool');
    }

    expect($caughtAsToolException)->toBeTrue();
});

test('it can be caught as ToolNotFoundException', function () {
    $caughtAsNotFound = false;

    try {
        throw ToolNotFoundException::forClass('NonExistent');
    } catch (ToolNotFoundException $e) {
        $caughtAsNotFound = true;
        expect($e->getMessage())->toContain('NonExistent');
    }

    expect($caughtAsNotFound)->toBeTrue();
});

test('forName preserves tool name with special characters', function () {
    $exception = ToolNotFoundException::forName('tool-with-dashes_and_underscores');

    expect($exception->getMessage())->toContain('tool-with-dashes_and_underscores');
});

test('forClass preserves full namespace path', function () {
    $exception = ToolNotFoundException::forClass('Vendor\\Package\\SubNamespace\\ToolClass');

    expect($exception->getMessage())->toContain('Vendor\\Package\\SubNamespace\\ToolClass');
});
