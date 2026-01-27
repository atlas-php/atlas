<?php

declare(strict_types=1);

use Atlasphp\Atlas\Contracts\PipelineContract;
use Atlasphp\Atlas\Pipelines\ConditionalPipelineHandler;
use Illuminate\Container\Container;

test('it executes handler when condition returns true', function () {
    $handler = new class implements PipelineContract
    {
        public static bool $executed = false;

        public function handle(mixed $data, Closure $next): mixed
        {
            self::$executed = true;

            return $next($data);
        }
    };

    $condition = fn ($data) => true;
    $conditional = new ConditionalPipelineHandler($handler, $condition);

    $conditional->handle(['test' => 'data'], fn ($data) => $data);

    expect($handler::$executed)->toBeTrue();
});

test('it skips handler when condition returns false', function () {
    $handler = new class implements PipelineContract
    {
        public static bool $executed = false;

        public function handle(mixed $data, Closure $next): mixed
        {
            self::$executed = true;

            return $next($data);
        }
    };
    $handler::$executed = false;

    $condition = fn ($data) => false;
    $conditional = new ConditionalPipelineHandler($handler, $condition);

    $conditional->handle(['test' => 'data'], fn ($data) => $data);

    expect($handler::$executed)->toBeFalse();
});

test('it passes data to next handler when condition is false', function () {
    $handler = new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            $data['modified'] = true;

            return $next($data);
        }
    };

    $condition = fn ($data) => false;
    $conditional = new ConditionalPipelineHandler($handler, $condition);

    $called = false;
    $result = $conditional->handle(['original' => 'data'], function ($data) use (&$called) {
        $called = true;

        return $data;
    });

    expect($called)->toBeTrue();
    expect($result)->toBe(['original' => 'data']);
    expect($result)->not->toHaveKey('modified');
});

test('it resolves class string handler from container', function () {
    $container = new Container;
    $container->bind(ConditionalTestHandler::class, fn () => new ConditionalTestHandler);

    $condition = fn ($data) => true;
    $conditional = new ConditionalPipelineHandler(
        ConditionalTestHandler::class,
        $condition,
        $container
    );

    $result = $conditional->handle(['count' => 0], fn ($data) => $data);

    expect($result['count'])->toBe(1);
});

test('it throws RuntimeException when resolving class string without container', function () {
    // Container is required to resolve class string handlers
    $condition = fn ($data) => true;
    $conditional = new ConditionalPipelineHandler(
        ConditionalTestHandler::class,
        $condition,
    );

    $conditional->handle(['count' => 0], fn ($data) => $data);
})->throws(RuntimeException::class, 'Container is required to resolve conditional pipeline handler class');

test('condition receives pipeline data', function () {
    $receivedData = null;
    $condition = function ($data) use (&$receivedData) {
        $receivedData = $data;

        return true;
    };

    $handler = new class implements PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            return $next($data);
        }
    };

    $conditional = new ConditionalPipelineHandler($handler, $condition);
    $conditional->handle(['test_key' => 'test_value', 'nested' => ['a' => 1]], fn ($data) => $data);

    expect($receivedData)->toBe(['test_key' => 'test_value', 'nested' => ['a' => 1]]);
});

test('it throws when class string handler does not implement contract', function () {
    $container = new Container;
    $container->bind(NotAPipelineHandler::class, fn () => new NotAPipelineHandler);

    $condition = fn ($data) => true;
    $conditional = new ConditionalPipelineHandler(
        NotAPipelineHandler::class,
        $condition,
        $container
    );

    $conditional->handle(['test' => 'data'], fn ($data) => $data);
})->throws(InvalidArgumentException::class, 'Conditional pipeline handler must implement');

// Test Helper Classes

class ConditionalTestHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $data['count'] = ($data['count'] ?? 0) + 1;

        return $next($data);
    }
}

class NotAPipelineHandler
{
    public function handle(mixed $data, Closure $next): mixed
    {
        return $next($data);
    }
}
