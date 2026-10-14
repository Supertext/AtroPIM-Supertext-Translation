<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Translation;

use Atro\Core\Container;
use Espo\ORM\Entity;

/**
 * The read-only fields of a Supertext connection: "API key check" (the latest automatic key check,
 * when it ran, and whether version information is sent) and "Languages" (each AtroCore language's
 * Supertext code, or "not mapped"). They are filled when the connection is shown, in the viewer's
 * language, and stored after a save or "Test connection", so the form shows the new result at once.
 */
final class ConnectionStatus
{
    public const KEY_CHECK_FIELD = 'supertextKeyCheck';
    public const LANGUAGES_FIELD = 'supertextLanguageStatus';
    public const REPORTS_FIELD = 'supertextVersionReports';

    /** Sets both fields on the entity (not saved). */
    public static function fill(Container $container, Entity $connection, Settings $settings, Reporting $reporting, ?Messages $messages = null): void
    {
        $messages ??= Messages::forUser($container);
        [$languages, $main] = self::languages($container);

        self::set($connection, self::KEY_CHECK_FIELD, $reporting->statusText($settings->versionReports(), $messages));
        self::set($connection, self::LANGUAGES_FIELD, $settings->languageStatus($languages, $main, $messages));

        // Connections from before the setting existed have no value: show its default (on), so the
        // form never presents an unticked box for reports that are actually sent.
        if ($settings->versionReports() === null) {
            self::set($connection, self::REPORTS_FIELD, true);
        }
    }

    /** Fills both fields and saves them without hooks (no history entry, no second report). */
    public static function store(Container $container, Entity $connection, Settings $settings, Reporting $reporting): void
    {
        try {
            self::fill($container, $connection, $settings, $reporting);
            $container->get('entityManager')->saveEntity($connection, ['skipAll' => true]);
        } catch (\Throwable) {
            // Only a display: the next view fills the fields again.
        }
    }

    /** @return array{0: list<string>, 1: string} the main language and the input languages, and the main language */
    public static function languages(Container $container): array
    {
        $config = $container->get('config');
        $main   = (string) $config->get('mainLanguage', 'en_US');

        return [array_values(array_unique(array_filter(array_merge([$main], (array) $config->get('inputLanguageList', []))))), $main];
    }

    private static function set(Entity $connection, string $field, string|bool $value): void
    {
        if (method_exists($connection, 'setDataField')) {
            $connection->setDataField($field, $value);

            return;
        }

        $connection->set($field, $value);
    }
}
