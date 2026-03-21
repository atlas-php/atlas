<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\ExecutionCompleted;
use Atlasphp\Atlas\Events\ExecutionFailed;
use Atlasphp\Atlas\Queue\Jobs\ExecuteAtlasJob;
use Atlasphp\Atlas\Queue\QueueableRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Event;
use Laravel\SerializableClosure\SerializableClosure;

it('calls executeFromPayload on the request class', function () {
    Event::fake();

    // Create a temporary test class that implements QueueableRequest
    $requestClass = new class implements QueueableRequest
    {
        public static bool $wasCalled = false;

        public static array $receivedArgs = [];

        public function toQueuePayload(): array
        {
            return [];
        }

        public static function executeFromPayload(
            array $payload,
            string $terminal,
            ?int $executionId = null,
            ?Channel $broadcastChannel = null,
        ): mixed {
            self::$wasCalled = true;
            self::$receivedArgs = compact('payload', 'terminal', 'executionId');

            return 'test-result';
        }
    };

    $className = get_class($requestClass);

    // Reset static state
    $className::$wasCalled = false;
    $className::$receivedArgs = [];

    $job = new ExecuteAtlasJob(
        requestClass: $className,
        terminal: 'asText',
        payload: ['message' => 'hello'],
        executionId: 7,
    );

    $job->handle();

    expect($className::$wasCalled)->toBeTrue();
    expect($className::$receivedArgs['terminal'])->toBe('asText');
    expect($className::$receivedArgs['payload'])->toBe(['message' => 'hello']);
    expect($className::$receivedArgs['executionId'])->toBe(7);
});

it('fires ExecutionCompleted event on success', function () {
    Event::fake([ExecutionCompleted::class]);

    $requestClass = new class implements QueueableRequest
    {
        public function toQueuePayload(): array
        {
            return [];
        }

        public static function executeFromPayload(
            array $payload,
            string $terminal,
            ?int $executionId = null,
            ?Channel $broadcastChannel = null,
        ): mixed {
            return 'ok';
        }
    };

    $job = new ExecuteAtlasJob(
        requestClass: get_class($requestClass),
        terminal: 'asText',
        payload: [],
        executionId: 42,
    );

    $job->handle();

    Event::assertDispatched(ExecutionCompleted::class, function (ExecutionCompleted $event) {
        return $event->executionId === 42;
    });
});

it('fires ExecutionFailed event on failure', function () {
    Event::fake([ExecutionFailed::class]);

    $job = new ExecuteAtlasJob(
        requestClass: 'Atlasphp\Atlas\Pending\TextRequest',
        terminal: 'asText',
        payload: [],
        executionId: 55,
    );

    $exception = new RuntimeException('Provider timeout');

    $job->failed($exception);

    Event::assertDispatched(ExecutionFailed::class, function (ExecutionFailed $event) {
        return $event->executionId === 55
            && $event->error === 'Provider timeout';
    });
});

it('reads config values for tries, backoff, timeout', function () {
    config()->set('atlas.queue.tries', 5);
    config()->set('atlas.queue.backoff', 60);
    config()->set('atlas.queue.timeout', 600);

    $job = new ExecuteAtlasJob(
        requestClass: 'Atlasphp\Atlas\Pending\TextRequest',
        terminal: 'asText',
        payload: [],
    );

    expect($job->tries)->toBe(5);
    expect($job->backoff)->toBe(60);
    expect($job->timeout)->toBe(600);
});

it('invokes thenCallback with result on success', function () {
    Event::fake();

    $callbackResult = null;

    $requestClass = new class implements QueueableRequest
    {
        public function toQueuePayload(): array
        {
            return [];
        }

        public static function executeFromPayload(
            array $payload,
            string $terminal,
            ?int $executionId = null,
            ?Channel $broadcastChannel = null,
        ): mixed {
            return 'success-value';
        }
    };

    $job = new ExecuteAtlasJob(
        requestClass: get_class($requestClass),
        terminal: 'asText',
        payload: [],
    );

    $job->thenCallback = new SerializableClosure(
        function ($result) use (&$callbackResult) {
            $callbackResult = $result;
        }
    );

    $job->handle();

    expect($callbackResult)->toBe('success-value');
});

it('invokes catchCallback with exception on failure', function () {
    Event::fake();

    $caughtException = null;

    $job = new ExecuteAtlasJob(
        requestClass: 'Atlasphp\Atlas\Pending\TextRequest',
        terminal: 'asText',
        payload: [],
    );

    $job->catchCallback = new SerializableClosure(
        function (Throwable $e) use (&$caughtException) {
            $caughtException = $e;
        }
    );

    $job->failed(new RuntimeException('Test failure'));

    expect($caughtException)->toBeInstanceOf(RuntimeException::class);
    expect($caughtException->getMessage())->toBe('Test failure');
});
