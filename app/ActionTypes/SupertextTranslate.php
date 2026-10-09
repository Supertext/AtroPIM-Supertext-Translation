<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\ActionTypes;

use Atro\ActionTypes\AbstractAction;
use Atro\Core\Exceptions\BadRequest;
use Atro\Entities\ActionExecution;
use Espo\ORM\Entity;
use SupertextTranslation\Api\SupertextException;
use SupertextTranslation\Translation\ConnectionResolver;
use SupertextTranslation\Translation\EntityTranslator;
use SupertextTranslation\Translation\Messages;

/**
 * Action type "Translate with Supertext". An administrator creates an Action of this type for
 * an entity (Product, Category, …); AtroCore then shows it as a button on the record and as a
 * mass action in the list, runs it in the background if "In background" is set, and records
 * every run under Action executions.
 *
 * Mass actions arrive here once per record (AbstractAction::useMassActions() is true).
 */
class SupertextTranslate extends AbstractAction
{
    public const TYPE = 'supertextTranslate';

    /** memoryStorage key: the results of this request's runs (one per record), for the success message. */
    public const SUMMARY_KEY = 'supertextTranslationRuns';

    public static function getTypeLabel(): ?string
    {
        return 'Translate with Supertext';
    }

    public static function getName(): ?string
    {
        return 'Translate with Supertext';
    }

    public static function getDescription(): ?string
    {
        return 'Translates the multilingual text fields of the record into the other languages with Supertext AI.';
    }

    public function execute(ActionExecution $execution, \stdClass $input): bool
    {
        /** @var Entity $action */
        $action     = $execution->get('action');
        $entityType = (string) $action->get('sourceEntity');
        $record     = $this->getSourceEntity($action, $input);

        if ($entityType === '' || !$record instanceof Entity) {
            throw new BadRequest(Messages::forUser($this->container)->text('needs_record'));
        }

        $messages   = Messages::forUser($this->container);
        $translator = new EntityTranslator($this->container, $messages);

        try {
            $results = $translator->translate(
                $record->getEntityType(),
                (string) $record->get('id'),
                (string) $action->get('supertextSourceLanguage'),
                self::languages($action->get('supertextTargetLanguages')),
                (bool) $action->get('supertextOverwrite'),
                (string) $action->get('supertextPoliteness'),
                (new ConnectionResolver($this->container))->settings($action->get('supertextConnectionId') ?: null),
            );
        } catch (SupertextException $e) {
            $message = $messages->exception($e);
            $this->log($execution, $record, 'error', $message);
            $this->remember(false, $message);

            throw new BadRequest($message);
        }

        $summary = EntityTranslator::summary($results, $messages);
        $errors  = array_filter($results, static fn (array $r): bool => $r['status'] === 'error');
        $done    = array_filter($results, static fn (array $r): bool => $r['status'] === 'translated');

        $this->log($execution, $record, $errors !== [] ? 'error' : 'update', $summary);
        $this->remember($errors === [], $summary);

        if ($errors !== [] && $done === []) {
            // Nothing was saved: report it as a failure, so the editor sees the reason.
            throw new BadRequest($summary);
        }

        $execution->set('status', 'done');
        $execution->set('statusMessage', $summary);
        $this->getEntityManager()->saveEntity($execution);

        return true;
    }

    private function remember(bool $ok, string $summary): void
    {
        $runs   = $this->getMemoryStorage()->get(self::SUMMARY_KEY);
        $runs   = \is_array($runs) ? $runs : [];
        $runs[] = ['ok' => $ok, 'summary' => $summary];
        $this->getMemoryStorage()->set(self::SUMMARY_KEY, $runs);
    }

    /** @return list<string> */
    public static function languages(mixed $value): array
    {
        if (\is_string($value)) {
            $value = json_decode($value, true) ?? preg_split('/[\s,]+/', $value);
        }

        return array_values(array_filter(array_map('strval', \is_array($value) ? $value : []), static fn (string $l): bool => $l !== ''));
    }

    private function log(ActionExecution $execution, Entity $record, string $type, string $message): void
    {
        try {
            $log = $this->getEntityManager()->getRepository('ActionExecutionLog')->get();
            $log->set([
                'actionExecutionId' => $execution->get('id'),
                'entityName'        => $record->getEntityType(),
                'entityId'          => $record->get('id'),
                'type'              => $type,
                'message'           => $message,
            ]);
            $this->getEntityManager()->saveEntity($log);
        } catch (\Throwable) {
            // The execution's own status message carries the result as well.
        }
    }
}
