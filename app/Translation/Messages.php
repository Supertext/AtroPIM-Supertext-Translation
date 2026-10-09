<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Translation;

use SupertextTranslation\Api\SupertextException;

/**
 * The messages editors see (action results, errors, "Test connection") in their interface
 * language. The texts are AtroCore translations in app/Resources/i18n/<language>/Action.json →
 * "messages", keys "supertext…" (e.g. the key no_api_key is "supertextNoApiKey"), with {name}
 * placeholders. Missing translations fall back to English; console commands use English.
 *
 * No AtroCore dependencies: the lookup is a closure (see forUser()).
 */
final class Messages
{
    public const SCOPE = 'Action';
    public const CATEGORY = 'messages';

    /** Keys after which the links to the Supertext signup and API key page follow. */
    private const KEY_HELP_AFTER = ['no_api_key', 'no_api_key_connection', 'enter_api_key', 'auth_failed'];

    /** @var array<string, string>|null */
    private static ?array $english = null;

    /** @param (\Closure(string): string)|null $lookup the text of a key in the user's language, or the key itself if there is none */
    public function __construct(private readonly ?\Closure $lookup = null)
    {
    }

    /** Messages in the current user's language (AtroCore's `language` service). */
    public static function forUser(object $container): self
    {
        try {
            $language = $container->get('language');
        } catch (\Throwable) {
            return new self();
        }

        return new self(static fn (string $name): string => (string) $language->translate($name, self::CATEGORY, self::SCOPE));
    }

    /** "no_api_key" → "supertextNoApiKey" */
    public static function name(string $key): string
    {
        return 'supertext' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
    }

    /** @param array<string, string|int> $params */
    public function text(string $key, array $params = []): string
    {
        $name = self::name($key);
        $text = $this->lookup !== null ? ($this->lookup)($name) : $name;

        if ($text === $name || trim($text) === '') {
            $text = self::englishTexts()[$name] ?? $key;
        }

        $replace = [];

        foreach ($params as $param => $value) {
            $replace['{' . $param . '}'] = (string) $value;
        }

        return strtr($text, $replace);
    }

    /** The exception's message for editors: its key's text, Supertext's own detail, and the account links where they help. */
    public function exception(SupertextException $e): string
    {
        if ($e->key === '' || !isset(self::englishTexts()[self::name($e->key)])) {
            return $e->getMessage();
        }

        $text = $this->text($e->key, $e->params) . ($e->detail !== '' ? ' (' . $e->detail . ')' : '');

        if (\in_array($e->key, self::KEY_HELP_AFTER, true) || \in_array($e->getCode(), [401, 403], true)) {
            $text .= ' ' . $this->text('key_help');
        }

        return $text;
    }

    /** @return array<string, string> */
    public static function englishTexts(): array
    {
        if (self::$english === null) {
            $data          = json_decode((string) @file_get_contents(__DIR__ . '/../Resources/i18n/en_US/' . self::SCOPE . '.json'), true);
            self::$english = array_filter(\is_array($data[self::CATEGORY] ?? null) ? $data[self::CATEGORY] : [], 'is_string');
        }

        return self::$english;
    }
}
