<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

use Atlasphp\Atlas\Events\ProviderRequestFailed;
use Atlasphp\Atlas\Events\ProviderRequesting;
use Atlasphp\Atlas\Events\ProviderResponded;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Http;

/**
 * Shared HTTP transport for all provider drivers.
 *
 * Sends requests, fires transport events, and handles timeouts.
 */
class HttpClient
{
    public function __construct(
        protected readonly Dispatcher $events,
    ) {}

    /**
     * Send a POST request and return the decoded JSON response.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $url, array $headers, array $body, int $timeout): array
    {
        $this->events->dispatch(new ProviderRequesting($url, $body));

        $response = Http::withHeaders($headers)->timeout($timeout)->post($url, $body);

        if ($response->failed()) {
            $this->events->dispatch(new ProviderRequestFailed($url, $response));
            $response->throw();
        }

        $data = $response->json() ?? [];
        $this->events->dispatch(new ProviderResponded($url, $data));

        return $data;
    }

    /**
     * Send a streaming POST request.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     */
    public function stream(string $url, array $headers, array $body, int $timeout): mixed
    {
        $this->events->dispatch(new ProviderRequesting($url, $body));

        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->withOptions(['stream' => true])
            ->post($url, $body);

        if ($response->failed()) {
            $this->events->dispatch(new ProviderRequestFailed($url, $response));
            $response->throw();
        }

        return $response;
    }
}
