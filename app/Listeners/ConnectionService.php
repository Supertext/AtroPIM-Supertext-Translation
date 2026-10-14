<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;
use Espo\ORM\Entity;
use SupertextTranslation\Translation\ConnectionResolver;
use SupertextTranslation\Translation\ConnectionStatus;
use SupertextTranslation\Translation\ReportingFactory;

/**
 * The Supertext connection is the module's settings page:
 *
 * - shown (afterReadEntity): the read-only fields "API key check" and "Languages" in the viewer's
 *   language, then (after the response) the reporter's onRequest: checks a changed key, reports an
 *   update;
 * - saved (afterCreateEntity, afterUpdateEntity): the automatic API key check and the
 *   `installed`/`settings_saved` report, then both fields are stored so the form shows the result.
 *
 * Nothing here may fail a read or a save: every step is wrapped.
 */
class ConnectionService extends AbstractListener
{
    public function afterReadEntity(Event $event): void
    {
        $entity = $event->getArgument('entity');

        if (!self::isSupertext($entity)) {
            return;
        }

        try {
            $settings = ConnectionResolver::fromEntity($entity, $this->getContainer());
            ConnectionStatus::fill($this->getContainer(), $entity, $settings, ReportingFactory::create($this->getContainer(), $settings));
            ReportingFactory::onRequestLater($this->getContainer(), $settings);
        } catch (\Throwable) {
            // The connection is shown without the status fields.
        }
    }

    public function beforeCreateEntity(Event $event): void
    {
        self::dropReadOnlyFields($event->getArgument('attachment'));
    }

    public function beforeUpdateEntity(Event $event): void
    {
        self::dropReadOnlyFields($event->getArgument('data'));
    }

    public function afterCreateEntity(Event $event): void
    {
        $this->afterSave($event->getArgument('entity'));
    }

    public function afterUpdateEntity(Event $event): void
    {
        $this->afterSave($event->getArgument('entity'));
    }

    private function afterSave(mixed $entity): void
    {
        if (!self::isSupertext($entity)) {
            return;
        }

        try {
            $settings  = ConnectionResolver::fromEntity($entity, $this->getContainer());
            $reporting = ReportingFactory::create($this->getContainer(), $settings);
            $reporting->afterSettingsSaved($settings->apiKey());
            ConnectionStatus::store($this->getContainer(), $entity, $settings, $reporting);
        } catch (\Throwable) {
            // Saving the connection never fails because of the key check or the report.
        }
    }

    private static function isSupertext(mixed $entity): bool
    {
        return $entity instanceof Entity && $entity->get('type') === ConnectionResolver::TYPE;
    }

    /** The status fields are written by the module only. */
    private static function dropReadOnlyFields(mixed $data): void
    {
        if ($data instanceof \stdClass) {
            unset($data->{ConnectionStatus::KEY_CHECK_FIELD}, $data->{ConnectionStatus::LANGUAGES_FIELD});
        }
    }
}
