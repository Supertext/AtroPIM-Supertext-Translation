<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Translation;

/**
 * One multilingual text field of a record, e.g. "name" with its language variants
 * "nameDeDe", "nameFrFr" (native fields and attribute values work the same way).
 */
final class TextUnit
{
    /**
     * @param array<string, string> $fieldsByLanguage language code (de_DE) => field name; the main language maps to $field
     */
    public function __construct(
        public readonly string $field,
        public readonly bool $html,
        public readonly array $fieldsByLanguage,
        public readonly string $label = '',
        public readonly ?int $maxLength = null,
    ) {
    }

    public function fieldFor(string $language): ?string
    {
        return $this->fieldsByLanguage[$language] ?? null;
    }
}
