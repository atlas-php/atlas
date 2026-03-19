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
     * Send a GET request and return the decoded JSON response.
     *
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function get(string $url, array $headers, int $timeout): array
    {
        $this->events->dispatch(new ProviderRequesting($url, []));

        $response = Http::withHeaders($headers)->timeout($timeout)->get($url);

        if ($response->failed()) {
            $this->events->dispatch(new ProviderRequestFailed($url, $response));
            $response->throw();
        }

        $data = $response->json() ?? [];
        $this->events->dispatch(new ProviderResponded($url, $data));

        return $data;
    }

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
     * Send a POST request and return the raw response body.
     *
     * Used for binary responses such as audio generation.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     */
    public function postRaw(string $url, array $headers, array $body, int $timeout): string
    {
        $this->events->dispatch(new ProviderRequesting($url, $body));

        $response = Http::withHeaders($headers)->timeout($timeout)->post($url, $body);

        if ($response->failed()) {
            $this->events->dispatch(new ProviderRequestFailed($url, $response));
            $response->throw();
        }

        $this->events->dispatch(new ProviderResponded($url, []));

        return $response->body();
    }

    /**
     * Send a multipart POST request with file attachments.
     *
     * Used for endpoints that require file uploads such as audio transcription.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $data
     * @param  array<int, array{name: string, contents: string, filename?: string}>  $attachments
     * @return array<string, mixed>
     */
    public function postMultipart(string $url, array $headers, array $data, array $attachments, int $timeout): array
    {
        $this->events->dispatch(new ProviderRequesting($url, $data));

        $pending = Http::withHeaders($headers)->timeout($timeout);

        foreach ($attachments as $attachment) {
            $pending = $pending->attach(
                $attachment['name'],
                $attachment['contents'],
                $attachment['filename'] ?? null,
            );
        }

        $response = $pending->post($url, $data);

        if ($response->failed()) {
            $this->events->dispatch(new ProviderRequestFailed($url, $response));
            $response->throw();
        }

        $result = $response->json() ?? [];
        $this->events->dispatch(new ProviderResponded($url, $result));

        return $result;
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
