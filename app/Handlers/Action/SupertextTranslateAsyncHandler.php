<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Handlers\Action;

use Atro\Core\Routing\Route;
use Atro\Handlers\Action\AbstractActionTypeAsyncHandler;

#[Route(
    path: '/Action/{id}/supertextTranslateAsync',
    methods: ['POST'],
    summary: 'Execute a Translate with Supertext action in the background',
    description: 'Schedules the translation as a background job and returns the job ID.',
    tag: 'Action',
    parameters: [
        ['name' => 'id', 'in' => 'path', 'required' => true, 'description' => 'Action record ID.', 'schema' => ['type' => 'string']],
    ],
    requestBody: [
        'required' => false,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'entityId' => ['type' => 'string', 'description' => 'ID of the record to translate.'],
                        'where'    => ['type' => 'array', 'description' => 'AtroCore filter: translate every matching record.', 'items' => ['type' => 'object']],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => ['description' => 'The action has been scheduled as a background job.', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['jobId' => ['type' => 'string']]]]]],
        404 => ['description' => 'Action record not found.'],
    ],
)]
class SupertextTranslateAsyncHandler extends AbstractActionTypeAsyncHandler
{
}
