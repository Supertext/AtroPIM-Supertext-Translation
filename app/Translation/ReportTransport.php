<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Translation;

use SupertextTranslation\PluginHealth\Http\HttpRequest;
use SupertextTranslation\PluginHealth\Http\HttpResponse;
use SupertextTranslation\PluginHealth\Http\HttpTransport;
use SupertextTranslation\Sdk\Http\HttpRequest as SdkRequest;
use SupertextTranslation\Sdk\Http\HttpTransport as SdkTransport;

/**
 * Sends Supertext Plugin Health's reports through an SDK transport (the one Reporting::create() is
 * given in tests), so the key check and the report share one fake. The two libraries have the same
 * HTTP shapes in their own namespaces; this only converts between them. A missing answer stays an
 * exception, which Plugin Health records as a failed report. Without a transport, both use curl.
 * No AtroCore dependencies.
 */
final class ReportTransport implements HttpTransport
{
    public function __construct(private readonly SdkTransport $transport)
    {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $response = $this->transport->send(new SdkRequest($request->method, $request->url, $request->headers, $request->body, $request->timeout));

        return new HttpResponse($response->status, $response->body, $response->headers);
    }
}
