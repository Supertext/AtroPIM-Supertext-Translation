<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Console;

use Atro\Console\AbstractConsole;
use Atro\Core\UserContext;
use SupertextTranslation\Api\SupertextException;
use SupertextTranslation\Translation\CommandArguments;
use SupertextTranslation\Translation\EntityTranslator;

/**
 * php console.php "supertext translate Product <id> [de_CH,fr_CH] [--overwrite] [--source=en_US] [--politeness=more|less]"
 */
class Translate extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Supertext Translation: translate one record, e.g. "supertext translate Product <id> de_CH,fr_CH --overwrite".';
    }

    public function run(array $data): void
    {
        $options    = CommandArguments::parse((string) ($data['args'] ?? ''));
        $container  = $this->getContainer();
        $translator = new EntityTranslator($container);

        // The console has no signed-in user: run as AtroCore's system user, like jobs do.
        $entityManager = $container->get('entityManager');
        $systemUser    = $entityManager->getRepository('User')->getGlobalSystemUser();
        $entityManager->setUser($systemUser);
        $container->get(UserContext::class)->set($systemUser);

        if ($options['entityType'] === '' || $options['id'] === '') {
            self::show('Usage: php console.php "supertext translate <Entity> <id> [languages] [--overwrite] [--source=<language>] [--politeness=more|less]"', self::ERROR, true);
        }

        try {
            $results = $translator->translate(
                $options['entityType'],
                $options['id'],
                $options['source'],
                $options['targets'],
                $options['overwrite'],
                $options['politeness'],
            );
        } catch (SupertextException $e) {
            self::show($e->getMessage(), self::ERROR, true);
        }

        $failed = array_filter($results, static fn (array $r): bool => $r['status'] === 'error');
        self::show(EntityTranslator::summary($results), $failed === [] ? self::SUCCESS : self::ERROR, $failed !== []);
    }
}
