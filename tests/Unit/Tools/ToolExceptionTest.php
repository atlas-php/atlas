<?php

declare(strict_types=1);

use Atlasphp\Atlas\Contracts\Tools\Exceptions\ToolException;

test('it creates exception for execution failed', function () {
    $exception = ToolException::executionFailed('calculator', 'Division by zero');

    expect($exception)->toBeInstanceOf(ToolException::class);
    expect($exception->getMessage())->toBe("Tool 'calculator' execution failed: Division by zero");
});

test('it creates exception for execution failed with complex reason', function () {
    $exception = ToolException::executionFailed(
        'api_call',
        'HTTP 500: Internal server error from remote service',
    );

    expect($exception)->toBeInstanceOf(ToolException::class);
    expect($exception->getMessage())->toContain('api_call');
    expect($exception->getMessage())->toContain('HTTP 500');
});

test('it creates exception for invalid configuration', function () {
    $exception = ToolException::invalidConfiguration('search', 'Missing API key');

    expect($exception)->toBeInstanceOf(ToolException::class);
    expect($exception->getMessage())->toBe("Tool 'search' has invalid configuration: Missing API key");
});

test('it creates exception for invalid configuration with detailed message', function () {
    $exception = ToolException::invalidConfiguration(
        'database_query',
        'Connection string must include host, port, and database name',
    );

    expect($exception)->toBeInstanceOf(ToolException::class);
    expect($exception->getMessage())->toContain('database_query');
    expect($exception->getMessage())->toContain('Connection string');
});

test('it creates exception for duplicate registration', function () {
    $exception = ToolException::duplicateRegistration('calculator');

    expect($exception)->toBeInstanceOf(ToolException::class);
    expect($exception->getMessage())->toBe("A tool with name 'calculator' has already been registered.");
});

test('it creates exception for invalid parameter', function () {
    $exception = ToolException::invalidParameter('calculator', 'divisor', 'Cannot be zero');

    expect($exception)->toBeInstanceOf(ToolException::class);
    expect($exception->getMessage())->toBe("Tool 'calculator' has invalid parameter 'divisor': Cannot be zero");
});

test('it creates exception for invalid parameter with type error', function () {
    $exception = ToolException::invalidParameter(
        'user_lookup',
        'user_id',
        'Expected integer, got string',
    );

    expect($exception)->toBeInstanceOf(ToolException::class);
    expect($exception->getMessage())->toContain('user_lookup');
    expect($exception->getMessage())->toContain('user_id');
    expect($exception->getMessage())->toContain('Expected integer');
});

test('it creates exception for invalid parameter with missing required', function () {
    $exception = ToolException::invalidParameter(
        'email_sender',
        'recipient',
        'Required parameter is missing',
    );

    expect($exception)->toBeInstanceOf(ToolException::class);
    expect($exception->getMessage())->toContain('email_sender');
    expect($exception->getMessage())->toContain('recipient');
    expect($exception->getMessage())->toContain('Required parameter');
});

test('it creates exception for invalid parameter with validation error', function () {
    $exception = ToolException::invalidParameter(
        'file_upload',
        'file_size',
        'Must be less than 10MB',
    );

    expect($exception)->toBeInstanceOf(ToolException::class);
    expect($exception->getMessage())->toContain('file_upload');
    expect($exception->getMessage())->toContain('file_size');
    expect($exception->getMessage())->toContain('10MB');
});

test('exception extends base Exception class', function () {
    $exception = ToolException::executionFailed('test', 'reason');

    expect($exception)->toBeInstanceOf(Exception::class);
});

test('exception can be thrown and caught', function () {
    $caught = false;

    try {
        throw ToolException::executionFailed('test_tool', 'Test failure');
    } catch (ToolException $e) {
        $caught = true;
        expect($e->getMessage())->toContain('test_tool');
    }

    expect($caught)->toBeTrue();
});

test('exception preserves tool name in all factory methods', function () {
    $toolName = 'my_special_tool';

    $executionFailed = ToolException::executionFailed($toolName, 'reason');
    $invalidConfig = ToolException::invalidConfiguration($toolName, 'reason');
    $invalidParam = ToolException::invalidParameter($toolName, 'param', 'reason');
    $duplicate = ToolException::duplicateRegistration($toolName);

    expect($executionFailed->getMessage())->toContain($toolName);
    expect($invalidConfig->getMessage())->toContain($toolName);
    expect($invalidParam->getMessage())->toContain($toolName);
    expect($duplicate->getMessage())->toContain($toolName);
});
