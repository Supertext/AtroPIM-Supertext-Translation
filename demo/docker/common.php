<?php

/** Demo only: helpers shared by install.php and setup.php. */

declare(strict_types=1);

/** AtroCore's default password rule (Administration → Settings → Password regex pattern). */
const DEMO_DEFAULT_PASSWORD_PATTERN = '^(?=.*[A-Z])(?=.*[\W_])(?=.*\d).{8,}$';

function demo_log(string $message): void
{
    fwrite(STDOUT, "[demo] $message\n");
}

/**
 * The e-mail and password of a demo account from <PREFIX>_EMAIL / <PREFIX>_PASSWORD, or
 * [null, null] (with a warning) when they are missing or the password breaks AtroCore's rule.
 * Only variable names are logged, never values.
 *
 * @return array{0: ?string, 1: ?string}
 */
function demo_account(string $prefix, string $pattern): array
{
    $email    = trim((string) getenv($prefix . '_EMAIL'));
    $password = (string) getenv($prefix . '_PASSWORD');

    if ($email === '' || $password === '') {
        demo_log("{$prefix}_EMAIL / {$prefix}_PASSWORD are not set: skipping that account.");

        return [null, null];
    }

    if ($pattern !== '' && !preg_match('~' . str_replace('~', '\~', $pattern) . '~', $password)) {
        demo_log("{$prefix}_PASSWORD doesn't meet AtroCore's password rule (at least 8 characters with an upper-case letter, a digit and a special character): skipping that account.");

        return [null, null];
    }

    return [$email, $password];
}
