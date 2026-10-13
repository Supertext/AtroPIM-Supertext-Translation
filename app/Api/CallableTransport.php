<?php

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Api;

use SupertextTranslation\Sdk\Http\HttpRequest;
use SupertextTranslation\Sdk\Http\HttpResponse;
use SupertextTranslation\Sdk\Http\HttpTransport;
use SupertextTranslation\Sdk\Http\TransportException;

/**
 * Sends the Supertext SDK's requests through a callable (tests use a fake). Every HTTP answer, 4xx and
 * 5xx included, goes back to the SDK; only a missing answer is an exception.
 */
final class CallableTransport implements HttpTransport
{
    /** @var callable(string, string, array<string, string>, ?string): array{status: int, body: string, headers?: array<string, string>} */
    private $send;

    /** @param callable(string $method, string $url, array<string, string> $headers, ?string $body): array{status: int, body: string, headers?: array<string, string>} $send */
    public function __construct(callable $send)
    {
        $this->send = $send;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        try {
            $response = ($this->send)($request->method, $request->url, $request->headers, $request->body);
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return new HttpResponse($response['status'], $response['body'], $response['headers'] ?? []);
    }
}
