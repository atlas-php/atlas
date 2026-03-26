<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Http;

use Atlasphp\Atlas\Events\ProviderRequestCompleted;
use Atlasphp\Atlas\Events\ProviderRequestFailed;
use Atlasphp\Atlas\Events\ProviderRequestStarted;
use Atlasphp\Atlas\Events\ProviderRetrying;
use Atlasphp\Atlas\RequestConfig;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shared HTTP transport for all provider drivers.
 *
 * Sends requests, fires transport events, and runs the retry loop
 * for transient failures and rate limits.
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
    public function get(string $url, array $headers, int $timeout): array
    {
        $response = $this->sendGet($url, $headers, $timeout);

        $data = $response->json() ?? [];
        $this->events->dispatch(new ProviderRequestCompleted($url, $data));

        return $data;
    }

    /**
     * Send a GET request and return the raw response body.
     *
     * Used for binary responses such as video content downloads.
     *
     * @param  array<string, string>  $headers
     */
    public function getRaw(string $url, array $headers, int $timeout): string
    {
        $response = $this->sendGet($url, $headers, $timeout);

        $this->events->dispatch(new ProviderRequestCompleted($url, []));

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
    public function post(string $url, array $headers, array $body, int $timeout, ?RequestConfig $config = null): array
    {
        return $this->withRetry($config, $url, function () use ($url, $headers, $body, $timeout) {
            $response = $this->sendPost($url, $headers, $body, $timeout);

            $data = $response->json() ?? [];
            $this->events->dispatch(new ProviderRequestCompleted($url, $data));

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
    public function postRaw(string $url, array $headers, array $body, int $timeout, ?RequestConfig $config = null): string
    {
        return $this->withRetry($config, $url, function () use ($url, $headers, $body, $timeout) {
            $response = $this->sendPost($url, $headers, $body, $timeout);

            $this->events->dispatch(new ProviderRequestCompleted($url, []));

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
    public function postMultipart(string $url, array $headers, array $data, array $attachments, int $timeout, ?RequestConfig $config = null): array
    {
        return $this->withRetry($config, $url, function () use ($url, $headers, $data, $attachments, $timeout) {
            $this->events->dispatch(new ProviderRequestStarted($url, $data));

            $pending = Http::withHeaders($headers)->timeout($timeout);

            foreach ($attachments as $attachment) {
                $pending = $pending->attach(
                    $attachment['name'],
                    $attachment['contents'],
                    $attachment['filename'] ?? null,
                );
            }

            $response = $pending->post($url, $data);
            $this->handleFailure($url, $response);

            $result = $response->json() ?? [];
            $this->events->dispatch(new ProviderRequestCompleted($url, $result));

            return $result;
        });
    }

    /**
     * Send a streaming POST request.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     */
    public function stream(string $url, array $headers, array $body, int $timeout, ?RequestConfig $config = null): Response
    {
        return $this->withRetry($config, $url, function () use ($url, $headers, $body, $timeout) {
            $this->events->dispatch(new ProviderRequestStarted($url, $body));

            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->withOptions(['stream' => true])
                ->post($url, $body);

            $this->handleFailure($url, $response);

            $this->events->dispatch(new ProviderRequestCompleted($url, []));

            return $response;
        });
    }

    // ─── Internal ─────────────────────────────────────────────────

    /**
     * Send a GET request, dispatch start event, and validate the response.
     *
     * @param  array<string, string>  $headers
     */
    private function sendGet(string $url, array $headers, int $timeout): Response
    {
        $this->events->dispatch(new ProviderRequestStarted($url, []));

        $response = Http::withHeaders($headers)->timeout($timeout)->get($url);
        $this->handleFailure($url, $response);

        return $response;
    }

    /**
     * Send a POST request, dispatch start event, and validate the response.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     */
    private function sendPost(string $url, array $headers, array $body, int $timeout): Response
    {
        $this->events->dispatch(new ProviderRequestStarted($url, $body));

        $response = Http::withHeaders($headers)->timeout($timeout)->post($url, $body);
        $this->handleFailure($url, $response);

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
    protected function withRetry(?RequestConfig $config, string $url, callable $callback): mixed
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

                $this->events->dispatch(new ProviderRetrying($url, $e, $attempt, $wait));

                if ($wait > 0) {
                    usleep($wait);
                }
            }
        }
    }

    /**
     * Dispatch failure event and throw if the response indicates an error.
     */
    private function handleFailure(string $url, Response $response): void
    {
        if ($response->failed()) {
            $this->events->dispatch(new ProviderRequestFailed($url, $response));
            $response->throw();
        }
    }
}
