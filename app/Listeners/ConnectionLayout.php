<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractLayoutListener;

/** Adds the Supertext fields to the Connection form; they are only visible for type "Supertext". */
class ConnectionLayout extends AbstractLayoutListener
{
    public const ROWS = [
        [['name' => 'supertextApiKey'], ['name' => 'supertextEnvironment']],
        [['name' => 'supertextApiUrl'], ['name' => 'supertextTimeout']],
        [['name' => 'supertextLanguageCodes', 'fullWidth' => true]],
    ];

    public function detail(Event $event): void
    {
        if ($this->isRelatedLayout($event)) {
            return;
        }

        $event->setArgument('result', ActionLayout::addRows($event->getArgument('result'), self::ROWS, 'supertextApiKey'));
    }
}
