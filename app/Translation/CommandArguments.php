<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Translation;

/**
 * Arguments of "supertext translate": <Entity> <id> [languages|all] [--overwrite] [--source=xx_XX] [--politeness=more|less].
 */
final class CommandArguments
{
    /**
     * @return array{entityType: string, id: string, targets: list<string>, overwrite: bool, source: string, politeness: string}
     */
    public static function parse(string $args): array
    {
        $result     = ['entityType' => '', 'id' => '', 'targets' => [], 'overwrite' => false, 'source' => '', 'politeness' => ''];
        $positional = [];

        foreach (preg_split('/\s+/', trim($args)) ?: [] as $arg) {
            if ($arg === '') {
                continue;
            }

            if ($arg === '--overwrite') {
                $result['overwrite'] = true;
            } elseif (str_starts_with($arg, '--source=')) {
                $result['source'] = substr($arg, 9);
            } elseif (str_starts_with($arg, '--politeness=')) {
                $result['politeness'] = substr($arg, 13);
            } else {
                $positional[] = $arg;
            }
        }

        $result['entityType'] = $positional[0] ?? '';
        $result['id']         = $positional[1] ?? '';

        if (isset($positional[2]) && $positional[2] !== 'all') {
            $result['targets'] = array_values(array_filter(explode(',', $positional[2])));
        }

        return $result;
    }
}
