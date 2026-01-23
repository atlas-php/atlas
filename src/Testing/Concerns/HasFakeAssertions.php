<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing\Concerns;

use Atlasphp\Atlas\Testing\Support\RecordedRequest;
use Closure;
use PHPUnit\Framework\Assert;

/**
 * Trait providing assertion methods for fake Atlas testing.
 *
 * Provides PHPUnit-compatible assertions for verifying agent
 * execution during tests.
 */
trait HasFakeAssertions
{
    /**
     * Assert that an agent was called.
     *
     * @param  string|null  $agentKey  Optional agent key to check.
     */
    public function assertCalled(?string $agentKey = null): void
    {
        $recorded = $this->getRecordedRequests();

        if ($agentKey === null) {
            Assert::assertNotEmpty(
                $recorded,
                'Expected at least one agent to be called, but none were.'
            );

            return;
        }

        $matching = $this->filterByAgent($recorded, $agentKey);

        Assert::assertNotEmpty(
            $matching,
            "Expected agent '{$agentKey}' to be called, but it was not."
        );
    }

    /**
     * Assert that an agent was called a specific number of times.
     *
     * @param  string  $agentKey  The agent key to check.
     * @param  int  $times  The expected number of calls.
     */
    public function assertCalledTimes(string $agentKey, int $times): void
    {
        $recorded = $this->getRecordedRequests();
        $matching = $this->filterByAgent($recorded, $agentKey);
        $actualCount = count($matching);

        Assert::assertSame(
            $times,
            $actualCount,
            "Expected agent '{$agentKey}' to be called {$times} time(s), but it was called {$actualCount} time(s)."
        );
    }

    /**
     * Assert that an agent was not called.
     *
     * @param  string  $agentKey  The agent key to check.
     */
    public function assertNotCalled(string $agentKey): void
    {
        $recorded = $this->getRecordedRequests();
        $matching = $this->filterByAgent($recorded, $agentKey);

        Assert::assertEmpty(
            $matching,
            "Expected agent '{$agentKey}' not to be called, but it was called ".count($matching).' time(s).'
        );
    }

    /**
     * Assert that no agents were called.
     */
    public function assertNothingCalled(): void
    {
        $recorded = $this->getRecordedRequests();

        Assert::assertEmpty(
            $recorded,
            'Expected no agents to be called, but '.count($recorded).' call(s) were made.'
        );
    }

    /**
     * Assert that a request was sent matching the callback.
     *
     * @param  Closure(RecordedRequest): bool  $callback  A callback that receives each RecordedRequest.
     */
    public function assertSent(Closure $callback): void
    {
        $recorded = $this->getRecordedRequests();

        foreach ($recorded as $request) {
            if ($callback($request) === true) {
                Assert::assertCount(count($recorded), $recorded); // Successful assertion

                return;
            }
        }

        Assert::fail('Expected a request matching the callback, but none were found.');
    }

    /**
     * Assert that a request was sent with specific context metadata.
     *
     * @param  string  $key  The metadata key.
     * @param  mixed  $value  Optional value to match.
     */
    public function assertSentWithContext(string $key, mixed $value = null): void
    {
        $this->assertSent(function (RecordedRequest $request) use ($key, $value): bool {
            return $request->hasMetadata($key, $value);
        });
    }

    /**
     * Assert that a request was sent with a schema.
     */
    public function assertSentWithSchema(): void
    {
        $this->assertSent(function (RecordedRequest $request): bool {
            return $request->hasSchema();
        });
    }

    /**
     * Assert that a request was sent with input containing the given string.
     *
     * @param  string  $needle  The string to search for in the input.
     */
    public function assertSentWithInput(string $needle): void
    {
        $this->assertSent(function (RecordedRequest $request) use ($needle): bool {
            return $request->inputContains($needle);
        });
    }

    /**
     * Get all recorded requests.
     *
     * @return array<int, RecordedRequest>
     */
    abstract protected function getRecordedRequests(): array;

    /**
     * Filter recorded requests by agent key.
     *
     * @param  array<int, RecordedRequest>  $requests
     * @return array<int, RecordedRequest>
     */
    private function filterByAgent(array $requests, string $agentKey): array
    {
        return array_filter(
            $requests,
            fn (RecordedRequest $request): bool => $request->agentKey() === $agentKey
        );
    }
}
