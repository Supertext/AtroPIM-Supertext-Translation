<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Console;

use Atro\Console\AbstractConsole;
use SupertextTranslation\Api\SupertextException;
use SupertextTranslation\Translation\ConnectionResolver;
use SupertextTranslation\Translation\EntityTranslator;
use SupertextTranslation\Translation\Messages;
use SupertextTranslation\Translation\Settings;

/**
 * php console.php supertext check: version, languages, connection, API key (checked against the API).
 */
class Check extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Supertext Translation: show the version and settings and check the API key.';
    }

    public function run(array $data): void
    {
        $container = $this->getContainer();
        $module    = $container->get('moduleManager')->getModule('SupertextTranslation');
        $version   = $module !== null ? $module->getVersion() : '';
        $translator = new EntityTranslator($container, new Messages());

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

        if ($source === '') {
            self::show('API key: missing. ' . Settings::KEY_HELP, self::ERROR, true);
        }

        self::show('API key: from ' . ($source === 'connection' ? 'the connection' : 'SUPERTEXT_API_KEY'));

        try {
            $settings->client()->validateApiKey();
        } catch (SupertextException $e) {
            self::show('API key check failed: ' . Settings::explain($e), self::ERROR, true);
        }

        self::show('API key accepted by Supertext.', self::SUCCESS);
    }
}
