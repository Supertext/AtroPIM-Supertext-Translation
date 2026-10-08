<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Handlers\Action;

use Atro\Core\Routing\Route;
use Atro\Handlers\Action\AbstractActionTypeSyncHandler;

#[Route(
    path: '/Action/{id}/supertextTranslate',
    methods: ['POST'],
    summary: 'Execute a Translate with Supertext action',
    description: 'Translates the record(s) synchronously with Supertext AI.',
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
        200 => ['description' => 'Execution result.', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'message' => ['type' => 'string', 'nullable' => true]]]]]],
        404 => ['description' => 'Action record not found.'],
    ],
)]
class SupertextTranslateHandler extends AbstractActionTypeSyncHandler
{
}
