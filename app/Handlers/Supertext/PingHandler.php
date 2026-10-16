<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Handlers\Supertext;

use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SupertextTranslation\Translation\ConnectionResolver;
use SupertextTranslation\Translation\Reporting;

/**
 * The public ping endpoint of the Supertext Commando Center: GET /api/supertext/ping?challenge=<c>,
 * no login, no session. Answers exactly what Supertext Plugin Health builds (InstallationReporter::pingResponse):
 * 200 {"ok":true,"plugin":"AtroPIM-Supertext-Translation","challenge":"<c>"}, 400 for a bad
 * challenge, 404 while version reports are off. Nothing else: no versions, no settings.
 */
#[Route(
    path: '/supertext/ping',
    methods: ['GET'],
    summary: 'Supertext Commando Center ping',
    description: 'Public reachability check for the Supertext Commando Center. Echoes a valid challenge; 404 while version reports are off.',
    tag: 'Supertext',
    auth: false,
    parameters: [
        ['name' => 'challenge', 'in' => 'query', 'required' => false, 'description' => 'Random string from the center ([A-Za-z0-9_-]{16,64}).', 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'The challenge, echoed.', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
        400 => ['description' => 'Missing or invalid challenge.', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
        404 => ['description' => 'Version reports are off.', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
    skipActionHistory: true,
)]
class PingHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $challenge = $request->getQueryParams()['challenge'] ?? null;
        $ping      = Reporting::ping(\is_string($challenge) ? $challenge : null, $this->versionReportsSetting());

        return new Response($ping->status, $ping->headers, $ping->body);
    }

    /** "Send version information to Supertext" of the first Supertext connection (null: not set or no connection). */
    private function versionReportsSetting(): ?bool
    {
        try {
            $connection = $this->getEntityManager()->getRepository('Connection')
                ->where(['type' => ConnectionResolver::TYPE])->order('createdAt', 'ASC')->findOne();
            $data = $connection !== null ? $connection->get('data') : null;
            $data = \is_object($data) || \is_array($data) ? json_decode((string) json_encode($data), true) : [];

            return \is_array($data) && isset($data['supertextVersionReports']) ? (bool) $data['supertextVersionReports'] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
