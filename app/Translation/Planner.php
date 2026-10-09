<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Translation;

/**
 * Which fields of a record are translated, and into which languages. No AtroCore dependencies.
 *
 * AtroCore stores a multilingual field as a base field in the main language ("name") plus one
 * field per additional language ("nameDeDe"), each marked with multilangField/multilangLocale.
 * Attribute values follow the same model, so products, their attributes and any other entity
 * are handled alike.
 */
final class Planner
{
    /** Field types that hold text; wysiwyg is HTML, the others plain text. */
    public const TEXT_TYPES = ['varchar' => false, 'text' => false, 'markdown' => false, 'wysiwyg' => true];

    /**
     * @param array<string, mixed> $fieldDefs field name => definition (entityDefs fields, attribute fields included; non-array entries are skipped)
     *
     * @return list<TextUnit>
     */
    public static function units(array $fieldDefs, string $mainLanguage): array
    {
        $units = [];

        foreach ($fieldDefs as $name => $defs) {
            if (!\is_array($defs) || empty($defs['isMultilang']) || !\array_key_exists((string) ($defs['type'] ?? ''), self::TEXT_TYPES)) {
                continue;
            }

            if (!empty($defs['readOnly']) || !empty($defs['disabled']) || !empty($defs['notStorable']) || !empty($defs['emHidden'])) {
                continue;
            }

            $languages = [$mainLanguage => (string) $name];

            foreach ($fieldDefs as $siblingName => $siblingDefs) {
                if (\is_array($siblingDefs) && ($siblingDefs['multilangField'] ?? null) === $name && !empty($siblingDefs['multilangLocale'])) {
                    $languages[(string) $siblingDefs['multilangLocale']] = (string) $siblingName;
                }
            }

            if (\count($languages) < 2) {
                continue;
            }

            $units[] = new TextUnit(
                (string) $name,
                self::TEXT_TYPES[(string) $defs['type']],
                $languages,
                (string) ($defs['label'] ?? ''),
                isset($defs['maxLength']) && (int) $defs['maxLength'] > 0 ? (int) $defs['maxLength'] : null,
            );
        }

        return $units;
    }

    /**
     * @param list<TextUnit>       $units
     * @param array<string, mixed> $values field name => current value
     *
     * @return array{translate: list<TextUnit>, existing: int, noSource: int}
     */
    public static function plan(array $units, array $values, string $source, string $target, bool $overwrite): array
    {
        $result = ['translate' => [], 'existing' => 0, 'noSource' => 0];

        foreach ($units as $unit) {
            $sourceField = $unit->fieldFor($source);
            $targetField = $unit->fieldFor($target);

            if ($sourceField === null || $targetField === null || $sourceField === $targetField) {
                continue;
            }

            if (!self::hasText($values[$sourceField] ?? null)) {
                $result['noSource']++;

                continue;
            }

            if (!$overwrite && self::hasText($values[$targetField] ?? null)) {
                $result['existing']++;

                continue;
            }

            $result['translate'][] = $unit;
        }

        return $result;
    }

    /**
     * All languages any of the units can be translated into.
     *
     * @param list<TextUnit> $units
     *
     * @return list<string>
     */
    public static function languages(array $units): array
    {
        $languages = [];

        foreach ($units as $unit) {
            foreach (array_keys($unit->fieldsByLanguage) as $language) {
                $languages[$language] = true;
            }
        }

        return array_keys($languages);
    }

    /** Text after removing tags and whitespace (an empty editor saves "<p></p>" or "<p><br></p>"). */
    public static function hasText(mixed $value): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{A0}", ' ', $text)) !== '';
    }

    /** de_DE -> de-DE */
    public static function supertextCode(string $language): string
    {
        return str_replace('_', '-', $language);
    }
}
