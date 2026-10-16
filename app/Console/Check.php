<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Console;

use Atro\Console\AbstractConsole;
use SupertextTranslation\PluginHealth\KeyCheck;
use SupertextTranslation\Translation\ConnectionResolver;
use SupertextTranslation\Translation\ConnectionStatus;
use SupertextTranslation\Translation\EntityTranslator;
use SupertextTranslation\Translation\Messages;
use SupertextTranslation\Translation\Reporting;
use SupertextTranslation\Translation\ReportingFactory;
use SupertextTranslation\Translation\Settings;

/**
 * php console.php supertext check: version, languages and their Supertext codes, connection, API key
 * (the automatic key check), version reports (sends a `check` report to the Commando Center).
 */
class Check extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Supertext Translation: show the version and settings, check the API key and send a check report.';
    }

    public function run(array $data): void
    {
        $container = $this->getContainer();
        $module    = $container->get('moduleManager')->getModule('SupertextTranslation');
        $version   = $module !== null ? $module->getVersion() : '';
        $messages  = new Messages();
        $translator = new EntityTranslator($container, $messages);

        self::show('Supertext Translation ' . ($version !== '' ? $version : '(version unknown)'), self::INFO);

        if (preg_match('/^v?(\d+\.\d+\.\d+)$/', $version, $m)) {
            self::show('Release notes: https://github.com/Supertext/AtroPIM-Supertext-Translation/releases/tag/v' . $m[1]);
        }

        self::show('Languages: ' . implode(', ', $translator->languages()) . ' (main: ' . $translator->mainLanguage() . ')');

        $resolver   = new ConnectionResolver($container);
        $connection = $resolver->connection(null);
        self::show('Connection: ' . ($connection !== null ? $connection->get('name') : 'none (type "Supertext" under Administration → Connections)'));

        $settings = $resolver->settings(null);
        self::show('API endpoint: ' . $settings->baseUrl());

        $source = $settings->apiKeySource();
        self::show('API key: ' . match ($source) {
            'connection'  => 'from the connection',
            'environment' => 'from SUPERTEXT_API_KEY',
            default       => 'missing. ' . Settings::KEY_HELP,
        });

        [$languages, $main] = ConnectionStatus::languages($container);
        self::show("Language mapping:\n  " . str_replace("\n", "\n  ", $settings->languageStatus($languages, $main, $messages)));

        // The automatic key check (cost-free GET features), stored for the connection page and sent
        // to the Supertext Commando Center as a `check` report unless version reports are off.
        $reporting = ReportingFactory::create($container, $settings);
        $result    = $reporting->check($settings->apiKey());
        $check     = $result->check ?? $reporting->latestCheck();

        self::show(Reporting::offByEnvironment($settings->versionReports())
            ? 'Version reports: off (SUPERTEXT_VERSION_REPORTS)'
            : ($reporting->isEnabled() ? 'Version reports: on (ping URL ' . $reporting->pingUrl() . ')' . ($result !== null && $result->event !== null ? ', report ' . ($result->sent ? 'sent' : 'not sent') : '') : 'Version reports: off'));

        if ($check === null || $check->status !== KeyCheck::OK) {
            self::show('API key check: ' . Reporting::checkText($check, $messages), self::ERROR, true);
        }

        self::show('API key accepted by Supertext.', self::SUCCESS);
    }
}
