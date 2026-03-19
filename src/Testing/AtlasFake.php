<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Closure;
use PHPUnit\Framework\Assert;

/**
 * Testing replacement for AtlasManager that intercepts all provider calls.
 *
 * Provides assertion methods for verifying requests were sent correctly.
 */
class AtlasFake extends AtlasManager
{
    /** @var array<string, FakeDriver> */
    private array $drivers = [];

    /**
     * @param  array<int, TextResponseFake|StreamResponseFake|StructuredResponseFake|ImageResponseFake|AudioResponseFake|VideoResponseFake|EmbeddingsResponseFake|ModerationResponseFake>  $responses
     */
    public function __construct(
        ProviderRegistryContract $registry,
        array $responses = [],
    ) {
        foreach (Provider::cases() as $provider) {
            $driver = new FakeDriver($provider->value, $responses);
            $this->drivers[$provider->value] = $driver;
            $registry->register($provider->value, fn () => $driver);
        }

        parent::__construct($registry);
    }

    /**
     * Get all recorded requests across all drivers.
     *
     * @return array<int, RecordedRequest>
     */
    public function recorded(): array
    {
        $recorded = [];

        foreach ($this->drivers as $driver) {
            $recorded = array_merge($recorded, $driver->recorded());
        }

        return $recorded;
    }

    /**
     * Access a specific FakeDriver by provider name.
     */
    public function driver(?string $provider = null): FakeDriver
    {
        $key = $provider ?? Provider::OpenAI->value;

        Assert::assertArrayHasKey($key, $this->drivers, "No fake driver registered for provider [{$key}].");

        return $this->drivers[$key];
    }

    /**
     * Assert that at least one request was sent.
     */
    public function assertSent(): void
    {
        Assert::assertNotEmpty($this->recorded(), 'Expected at least one request to be sent, but none were recorded.');
    }

    /**
     * Assert that no requests were sent.
     */
    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->recorded(), 'Expected no requests to be sent, but '.count($this->recorded()).' were recorded.');
    }

    /**
     * Assert that an exact number of requests were sent.
     */
    public function assertSentCount(int $count): void
    {
        $actual = count($this->recorded());

        Assert::assertSame($count, $actual, "Expected {$count} requests to be sent, but {$actual} were recorded.");
    }

    /**
     * Assert that a request matching the callback was sent.
     */
    public function assertSentWith(Closure $callback): void
    {
        $matching = array_filter($this->recorded(), fn (RecordedRequest $request) => $callback($request) === true);

        Assert::assertNotEmpty($matching, 'Expected a matching request to be sent, but none matched the callback.');
    }

    /**
     * Assert that a request was sent to a specific provider and model.
     */
    public function assertSentTo(Provider|string $provider, string $model): void
    {
        $providerKey = $provider instanceof Provider ? $provider->value : $provider;

        $matching = array_filter(
            $this->recorded(),
            fn (RecordedRequest $request) => $request->provider === $providerKey && $request->model === $model,
        );

        Assert::assertNotEmpty($matching, "Expected a request to be sent to provider [{$providerKey}] with model [{$model}], but none was found.");
    }

    /**
     * Assert that a specific method was called.
     */
    public function assertMethodCalled(string $method): void
    {
        $matching = array_filter(
            $this->recorded(),
            fn (RecordedRequest $request) => $request->method === $method,
        );

        Assert::assertNotEmpty($matching, "Expected the [{$method}] method to be called, but it was not.");
    }

    /**
     * Assert that a request was sent with instructions containing the given text.
     */
    public function assertInstructionsContain(string $text): void
    {
        $matching = array_filter($this->recorded(), function (RecordedRequest $request) use ($text) {
            $inner = $request->request;

            if (! is_object($inner) || ! property_exists($inner, 'instructions')) {
                return false;
            }

            return is_string($inner->instructions) && str_contains($inner->instructions, $text);
        });

        Assert::assertNotEmpty($matching, "Expected a request with instructions containing [{$text}], but none was found.");
    }

    /**
     * Assert that a request was sent with a message containing the given text.
     */
    public function assertMessageContains(string $text): void
    {
        $matching = array_filter($this->recorded(), function (RecordedRequest $request) use ($text) {
            $inner = $request->request;

            if (! is_object($inner) || ! property_exists($inner, 'message')) {
                return false;
            }

            return is_string($inner->message) && str_contains($inner->message, $text);
        });

        Assert::assertNotEmpty($matching, "Expected a request with a message containing [{$text}], but none was found.");
    }
}
