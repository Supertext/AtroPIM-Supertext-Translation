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
use SupertextTranslation\PluginHealth\KeyCheck;
use SupertextTranslation\Translation\ConnectionResolver;
use SupertextTranslation\Translation\ConnectionStatus;
use SupertextTranslation\Translation\Messages;
use SupertextTranslation\Translation\Reporting;
use SupertextTranslation\Translation\ReportingFactory;
use SupertextTranslation\Translation\Settings;

/**
 * Connection type "Supertext" (Administration → Connections): holds the API key (encrypted by
 * AtroCore like every password field) and the API settings. "Test connection" runs the automatic
 * API key check (cost-free GET features), stores its result for the connection page and reports a
 * changed result to the Supertext Commando Center (see Translation\Reporting).
 */
class ConnectionSupertext extends AbstractConnection implements ConnectionInterface, TestConnectionInterface
{
    public function connect(Entity $connectionEntity): Settings
    {
        return ConnectionResolver::fromEntity($connectionEntity, $this->container);
    }

    public function testConnection(Entity $connectionEntity): bool
    {
        $settings  = $this->connect($connectionEntity);
        $messages  = Messages::forUser($this->container);
        $reporting = ReportingFactory::create($this->container, $settings);
        $check     = $reporting->testConnection($settings->apiKey());

        ConnectionStatus::store($this->container, $connectionEntity, $settings, $reporting);

        if ($check === null || $check->status !== KeyCheck::OK) {
            throw new BadRequest(Reporting::checkText($check, $messages));
        }

        return true;
    }
}
