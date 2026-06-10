<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Http;

use Atlasphp\Atlas\Events\ProviderRequestCompleted;
use Atlasphp\Atlas\Events\ProviderRequestFailed;
use Atlasphp\Atlas\Events\ProviderRequestRetrying;
use Atlasphp\Atlas\Events\ProviderRequestStarted;
use Atlasphp\Atlas\RequestConfig;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Shared HTTP transport for all provider drivers.
 *
 * Sends requests, fires transport events, and runs the retry loop
 * for transient failures and rate limits.
 *
 * Every logical call is stamped with a correlation id (stable across retries)
 * and the caller's provider/model context, which ride on all four transport
 * events so consumers can attribute and correlate a request's lifecycle.
 */
class HttpClient
{
    public function __construct(
        protected readonly Dispatcher $events,
        protected readonly RetryDecider $decider,
    ) {}

    /**
     * Send a GET request and return the decoded JSON response.
     *
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function get(string $url, array $headers, int $timeout, ?ProviderRequestContext $context = null): array
    {
        $context = $this->stamp($context);
        $response = $this->sendGet($url, $headers, $timeout, $context);

        $data = $response->json() ?? [];
        $this->dispatchCompleted($url, $data, $response->status(), $context);

        return $data;
    }

    /**
     * Send a GET request and return the raw response body.
     *
     * Used for binary responses such as video content downloads. Like get(),
     * this takes no RequestConfig — GET fetches are not retried and don't honor
     * a per-call ->withTimeout() override; they use the handler's media timeout.
     *
     * @param  array<string, string>  $headers
     */
    public function getRaw(string $url, array $headers, int $timeout, ?ProviderRequestContext $context = null): string
    {
        $context = $this->stamp($context);
        $response = $this->sendGet($url, $headers, $timeout, $context);

        $this->dispatchCompleted($url, [], $response->status(), $context);

        return $response->body();
    }

    /**
     * Send a POST request and return the decoded JSON response.
     *
     * Retries on rate limits (429) and transient errors (5xx) according
     * to the RequestConfig. Permanent failures surface immediately.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $url, array $headers, array $body, int $timeout, ?RequestConfig $config = null, ?ProviderRequestContext $context = null): array
    {
        $timeout = $this->effectiveTimeout($timeout, $config);
        $context = $this->stamp($context);

        return $this->withRetry($config, $url, $context, function () use ($url, $headers, $body, $timeout, $context) {
            $response = $this->sendPost($url, $headers, $body, $timeout, $context);

            $data = $response->json() ?? [];
            $this->dispatchCompleted($url, $data, $response->status(), $context);

            return $data;
        });
    }

    /**
     * Send a POST request and return the raw response body.
     *
     * Used for binary responses such as audio generation.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     */
    public function postRaw(string $url, array $headers, array $body, int $timeout, ?RequestConfig $config = null, ?ProviderRequestContext $context = null): string
    {
        $timeout = $this->effectiveTimeout($timeout, $config);
        $context = $this->stamp($context);

        return $this->withRetry($config, $url, $context, function () use ($url, $headers, $body, $timeout, $context) {
            $response = $this->sendPost($url, $headers, $body, $timeout, $context);

            $this->dispatchCompleted($url, [], $response->status(), $context);

            return $response->body();
        });
    }

    /**
     * Send a multipart POST request with file attachments.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $data
     * @param  array<int, array{name: string, contents: string, filename?: string}>  $attachments
     * @return array<string, mixed>
     */
    public function postMultipart(string $url, array $headers, array $data, array $attachments, int $timeout, ?RequestConfig $config = null, ?ProviderRequestContext $context = null): array
    {
        $timeout = $this->effectiveTimeout($timeout, $config);
        $context = $this->stamp($context);

        return $this->withRetry($config, $url, $context, function () use ($url, $headers, $data, $attachments, $timeout, $context) {
            $this->dispatchStarted($url, $data, 'MULTIPART', $context);

            $pending = Http::withHeaders($headers)->timeout($timeout);

            foreach ($attachments as $attachment) {
                $pending = $pending->attach(
                    $attachment['name'],
                    $attachment['contents'],
                    $attachment['filename'] ?? null,
                );
            }

            $response = $pending->post($url, $data);
            $this->handleFailure($url, $response, $context);

            $result = $response->json() ?? [];
            $this->dispatchCompleted($url, $result, $response->status(), $context);

            return $result;
        });
    }

    /**
     * Send a streaming POST request.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     */
    public function stream(string $url, array $headers, array $body, int $timeout, ?RequestConfig $config = null, ?ProviderRequestContext $context = null): Response
    {
        $timeout = $this->effectiveTimeout($timeout, $config);
        $context = $this->stamp($context);

        return $this->withRetry($config, $url, $context, function () use ($url, $headers, $body, $timeout, $context) {
            $this->dispatchStarted($url, $body, 'STREAM', $context);

            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->withOptions(['stream' => true])
                ->post($url, $body);

            $this->handleFailure($url, $response, $context);

            $this->dispatchCompleted($url, [], $response->status(), $context);

            return $response;
        });
    }

    // ─── Internal ─────────────────────────────────────────────────

    /**
     * Stamp the (possibly absent) caller context with a fresh correlation id.
     * Generated once per logical call so it stays stable across retries.
     */
    private function stamp(?ProviderRequestContext $context): ProviderRequestContext
    {
        return ($context ?? new ProviderRequestContext)->withCorrelationId((string) Str::uuid());
    }

    /**
     * Resolve the timeout for a call.
     *
     * The handler's $timeout is the default (provider/reasoning/media). A
     * per-call ->withTimeout() override wins only when explicitly set, so it
     * never clobbers a longer provider-specific default.
     */
    private function effectiveTimeout(int $timeout, ?RequestConfig $config): int
    {
        return $config?->timeoutExplicit ? $config->timeout : $timeout;
    }

    /**
     * Send a GET request, dispatch start event, and validate the response.
     *
     * @param  array<string, string>  $headers
     */
    private function sendGet(string $url, array $headers, int $timeout, ProviderRequestContext $context): Response
    {
        $this->dispatchStarted($url, [], 'GET', $context);

        $response = Http::withHeaders($headers)->timeout($timeout)->get($url);
        $this->handleFailure($url, $response, $context);

        return $response;
    }

    /**
     * Send a POST request, dispatch start event, and validate the response.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     */
    private function sendPost(string $url, array $headers, array $body, int $timeout, ProviderRequestContext $context): Response
    {
        $this->dispatchStarted($url, $body, 'POST', $context);

        $response = Http::withHeaders($headers)->timeout($timeout)->post($url, $body);
        $this->handleFailure($url, $response, $context);

        return $response;
    }

    /**
     * Execute a callable with retry logic.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withRetry(?RequestConfig $config, string $url, ProviderRequestContext $context, callable $callback): mixed
    {
        if ($config === null || ! $config->retryEnabled()) {
            return $callback();
        }

        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return $callback();
            } catch (\Throwable $e) {
                if (! $this->decider->shouldRetry($e, $config, $attempt)) {
                    throw $e;
                }

                $wait = $this->decider->waitMicroseconds($e, $attempt);

                $this->events->dispatch(new ProviderRequestRetrying(
                    $url, $e, $attempt, $wait, $context->correlationId, $context->provider, $context->model,
                ));

                if ($wait > 0) {
                    usleep($wait);
                }
            }
        }
    }

    /**
     * Dispatch failure event and throw if the response indicates an error.
     */
    private function handleFailure(string $url, Response $response, ProviderRequestContext $context): void
    {
        if ($response->failed()) {
            $this->events->dispatch(new ProviderRequestFailed(
                $url, $response, $context->correlationId, $context->provider, $context->model,
            ));
            $response->throw();
        }
    }

    /**
     * Dispatch the request-started event with correlation/provider/model context.
     *
     * @param  array<string, mixed>  $body
     */
    private function dispatchStarted(string $url, array $body, string $method, ProviderRequestContext $context): void
    {
        $this->events->dispatch(new ProviderRequestStarted(
            $url, $body, $method, $context->correlationId, $context->provider, $context->model,
        ));
    }

    /**
     * Dispatch the request-completed event with correlation/provider/model context.
     *
     * @param  array<string, mixed>  $data
     */
    private function dispatchCompleted(string $url, array $data, int $statusCode, ProviderRequestContext $context): void
    {
        $this->events->dispatch(new ProviderRequestCompleted(
            $url, $data, $statusCode, $context->correlationId, $context->provider, $context->model,
        ));
    }
}
