<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Translation;

use Atro\Core\Container;
use Atro\Core\Exceptions\NotModified;
use Espo\ORM\Entity;
use SupertextTranslation\Api\HtmlDocument;
use SupertextTranslation\Api\SupertextClient;
use SupertextTranslation\Api\SupertextException;

/**
 * Translates the multilingual text fields of one record (a product, a category, …) from a
 * source language into target languages: one Supertext document per target language, saved
 * through the entity's record service, so AtroCore's permissions, validation and change
 * history apply exactly as when an editor types the text.
 *
 * Field rules (keep docs/DEVELOPER.md → Field rules and docs/USER_GUIDE.md in sync): see
 * Planner::units() — multilingual varchar, text, markdown and wysiwyg fields, native ones and
 * attribute values alike; read-only fields are skipped.
 */
final class EntityTranslator
{
    public function __construct(private readonly Container $container)
    {
    }

    public function mainLanguage(): string
    {
        return (string) $this->container->get('config')->get('mainLanguage', 'en_US');
    }

    /** @return list<string> the main language and the additional input languages */
    public function languages(): array
    {
        $config = $this->container->get('config');

        return array_values(array_unique(array_merge([$this->mainLanguage()], (array) $config->get('inputLanguageList', []))));
    }

    /**
     * @param list<string> $targets empty: every language except the source
     *
     * @return array<string, array{status: string, translated: int, existing: int, message: string}>
     *         status: "translated", "nothing" (no text, or all kept) or "error"
     */
    public function translate(
        string $entityType,
        string $id,
        string $source,
        array $targets,
        bool $overwrite,
        string $politeness = '',
        ?Settings $settings = null,
    ): array {
        $service = $this->container->get('serviceFactory')->create($entityType);
        $entity  = $service->getEntity($id);

        if (!$entity instanceof Entity) {
            throw new SupertextException(sprintf('%s %s was not found.', $entityType, $id));
        }

        $units  = Planner::units($this->fieldDefs($entity), $this->mainLanguage());
        $source = $source !== '' ? $source : $this->mainLanguage();

        if ($units === []) {
            throw new SupertextException(sprintf('%s has no multilingual text fields. Make the fields multilingual and add languages under Administration → Languages.', $entityType));
        }

        $available = Planner::languages($units);

        if (!\in_array($source, $available, true)) {
            throw new SupertextException(sprintf('"%s" is not one of the languages of this record (%s).', $source, implode(', ', $available)));
        }

        $targets = $targets === [] ? array_values(array_diff($available, [$source])) : array_values(array_intersect(array_unique($targets), $available));
        $targets = array_values(array_diff($targets, [$source]));

        $settings ??= (new ConnectionResolver($this->container))->settings(null);
        $client   = $settings->client();

        if (!$client->hasApiKey()) {
            throw new SupertextException('No Supertext API key is configured. Create a connection of type "Supertext" under Administration → Connections. ' . Settings::KEY_HELP);
        }

        $results = [];

        foreach ($targets as $target) {
            $values = $this->values($entity, $units);
            $plan   = Planner::plan($units, $values, $source, $target, $overwrite);

            if ($plan['translate'] === []) {
                $results[$target] = ['status' => 'nothing', 'translated' => 0, 'existing' => $plan['existing'], 'message' => ''];

                continue;
            }

            try {
                $translations = $this->translateUnits($client, $settings, $plan['translate'], $values, $source, $target, $politeness);
            } catch (SupertextException $e) {
                $results[$target] = ['status' => 'error', 'translated' => 0, 'existing' => $plan['existing'], 'message' => Settings::explain($e)];

                continue;
            }

            $data    = new \stdClass();
            $tooLong = [];

            foreach ($plan['translate'] as $index => $unit) {
                $text = $translations[$index] ?? '';

                if (!Planner::hasText($text)) {
                    continue;
                }

                if ($unit->maxLength !== null && !$unit->html && mb_strlen($text) > $unit->maxLength) {
                    $tooLong[] = $unit->label !== '' ? $unit->label : $unit->field;

                    continue;
                }

                $data->{$unit->fieldFor($target)} = $text;
            }

            if ((array) $data === []) {
                $results[$target] = [
                    'status'     => 'error',
                    'translated' => 0,
                    'existing'   => $plan['existing'],
                    'message'    => $tooLong !== [] ? 'The translation is longer than the field allows: ' . implode(', ', $tooLong) . '.' : 'Supertext returned no text.',
                ];

                continue;
            }

            try {
                $service->updateEntity($id, $data);
            } catch (NotModified) {
                // The record already holds exactly this translation.
            } catch (\Throwable $e) {
                $reason = trim($e->getMessage()) !== '' ? $e->getMessage() : (new \ReflectionClass($e))->getShortName();
                $results[$target] = ['status' => 'error', 'translated' => 0, 'existing' => $plan['existing'], 'message' => 'AtroPIM did not accept the translation: ' . $reason];

                continue;
            }

            $entity = $service->getEntity($id) ?? $entity;
            $results[$target] = [
                'status'     => 'translated',
                'translated' => \count((array) $data),
                'existing'   => $plan['existing'],
                'message'    => $tooLong !== [] ? 'Not translated because the translation is too long: ' . implode(', ', $tooLong) . '.' : '',
            ];
        }

        return $results;
    }

    /**
     * One line per outcome, e.g. "de_CH, fr_CH: translated (3 fields)".
     *
     * @param array<string, array{status: string, translated: int, existing: int, message: string}> $results
     */
    public static function summary(array $results): string
    {
        // Languages with the same outcome share a line: "de_CH, fr_CH: translated (3 fields)".
        $groups = [];

        foreach ($results as $language => $result) {
            $outcome = match ($result['status']) {
                'translated' => sprintf('translated (%d %s)%s', $result['translated'], $result['translated'] === 1 ? 'field' : 'fields', $result['message'] !== '' ? '. ' . $result['message'] : ''),
                'nothing'    => $result['existing'] > 0 ? 'kept, already translated' : 'nothing to translate',
                default      => 'error: ' . $result['message'],
            };
            $groups[$outcome][] = $language;
        }

        $lines = [];

        foreach ($groups as $outcome => $languages) {
            $lines[] = implode(', ', $languages) . ': ' . $outcome;
        }

        return $lines === [] ? 'No other languages to translate into.' : implode("\n", $lines);
    }

    /**
     * The message shown after the action ran: the summary for one record, counts for several.
     *
     * @param list<array{ok: bool, summary: string}> $runs
     */
    public static function runsMessage(array $runs): string
    {
        if (\count($runs) === 1) {
            return str_replace("\n", '; ', $runs[0]['summary']);
        }

        $failed = \count(array_filter($runs, static fn (array $run): bool => !$run['ok']));

        return sprintf('Supertext translated %d of %d records.', \count($runs) - $failed, \count($runs))
            . ($failed > 0 ? ' The Executions panel of the action shows why.' : '');
    }

    /** @return array<string, array<string, mixed>> */
    private function fieldDefs(Entity $entity): array
    {
        $defs = $this->container->get('metadata')->get(['entityDefs', $entity->getEntityType(), 'fields'], []);

        // Attribute values are only in the loaded entity's own definitions.
        if (property_exists($entity, 'entityDefs') && \is_array($entity->entityDefs['fields'] ?? null)) {
            $defs = array_merge($defs, $entity->entityDefs['fields']);
        }

        return $defs;
    }

    /**
     * @param list<TextUnit> $units
     *
     * @return array<string, mixed>
     */
    private function values(Entity $entity, array $units): array
    {
        $values = [];

        foreach ($units as $unit) {
            foreach ($unit->fieldsByLanguage as $field) {
                $values[$field] = $entity->get($field);
            }
        }

        return $values;
    }

    /**
     * @param list<TextUnit>       $units
     * @param array<string, mixed> $values
     *
     * @return array<int, string> unit index => translated text
     */
    private function translateUnits(SupertextClient $client, Settings $settings, array $units, array $values, string $source, string $target, string $politeness): array
    {
        $segments = [];
        $isHtml   = [];

        foreach ($units as $index => $unit) {
            $segments[$index] = ['text' => (string) $values[$unit->fieldFor($source)], 'html' => $unit->html];
            $isHtml[$index]   = $unit->html;
        }

        $translations = [];

        foreach (HtmlDocument::chunks($segments) as $chunk) {
            $html = $client->translateDocument(
                HtmlDocument::build($chunk),
                $settings->languageCode($target),
                $settings->languageCode($source),
                \in_array($politeness, ['more', 'less'], true) ? $politeness : 'default',
            );
            $translations += HtmlDocument::parse($html, $isHtml);
        }

        return $translations;
    }
}
