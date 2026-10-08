<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\ConnectionType;

use Atro\ConnectionType\AbstractConnection;
use Atro\ConnectionType\ConnectionInterface;
use Atro\ConnectionType\TestConnectionInterface;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use SupertextTranslation\Api\SupertextException;
use SupertextTranslation\Translation\ConnectionResolver;
use SupertextTranslation\Translation\Settings;

/**
 * Connection type "Supertext" (Administration → Connections): holds the API key (encrypted by
 * AtroCore like every password field) and the API settings. "Test connection" checks the key.
 */
class ConnectionSupertext extends AbstractConnection implements ConnectionInterface, TestConnectionInterface
{
    public function connect(Entity $connectionEntity): Settings
    {
        return ConnectionResolver::fromEntity($connectionEntity, $this->container);
    }

    public function testConnection(Entity $connectionEntity): bool
    {
        $settings = $this->connect($connectionEntity);

        if ($settings->apiKey() === '') {
            throw new BadRequest('Enter the Supertext API key. ' . Settings::KEY_HELP);
        }

        try {
            $settings->client()->validateApiKey();
        } catch (SupertextException $e) {
            throw new BadRequest(Settings::explain($e));
        }

        return true;
    }
}
