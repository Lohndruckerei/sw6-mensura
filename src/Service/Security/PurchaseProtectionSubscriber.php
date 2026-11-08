<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Security;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeEntityAggregationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Serverseitiger Schutz der Einkaufspreise (KONZEPT 3b): Benutzer der Administration ohne Recht
 * `ld_mensura.purchase` (und ohne Admin-Rolle) sehen keine EK-Werte, können nicht danach suchen und sie
 * nicht ändern – auch nicht direkt über die Admin-API. Shop, Warenkorb und Systemprozesse sind nicht betroffen.
 * Regeln und Felder: PurchaseFieldGuard.
 */
class PurchaseProtectionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        $events = [
            EntitySearchedEvent::class => 'onSearch',
            BeforeEntityAggregationEvent::class => 'onAggregate',
            EntityWriteEvent::class => 'onWrite',
        ];
        foreach (array_keys(PurchaseFieldGuard::FIELDS) as $entity) {
            $events[$entity . '.loaded'] = 'onLoaded';
            $events[$entity . '.partial_loaded'] = 'onLoaded';
        }

        return $events;
    }

    /** Darf dieser Kontext keine Einkaufspreise sehen? */
    public static function isRestricted(Context $context): bool
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || $context->hasState(PurchaseFieldGuard::STATE_INTERNAL)) {
            return false;
        }

        return !$source->isAdmin() && !$source->isAllowed(PurchaseFieldGuard::PRIVILEGE);
    }

    public function onLoaded(EntityLoadedEvent $event): void
    {
        if (!self::isRestricted($event->getContext())) {
            return;
        }
        $entityName = $event->getDefinition()->getEntityName();
        foreach ($event->getEntities() as $entity) {
            if (!$entity instanceof Entity) {
                continue;
            }
            $values = [];
            foreach (array_keys(PurchaseFieldGuard::FIELDS[$entityName] ?? []) as $property) {
                if ($entity->has($property)) {
                    $values[$property] = $entity->get($property);
                }
            }
            if ($values !== []) {
                $entity->assign(PurchaseFieldGuard::strip($entityName, $values));
            }
        }
    }

    public function onSearch(EntitySearchedEvent $event): void
    {
        $this->checkCriteria($event->getDefinition()->getEntityName(), $event->getCriteria()->getAllFields(), $event->getContext());
    }

    public function onAggregate(BeforeEntityAggregationEvent $event): void
    {
        $this->checkCriteria($event->getDefinition()->getEntityName(), $event->getCriteria()->getAllFields(), $event->getContext());
    }

    public function onWrite(EntityWriteEvent $event): void
    {
        if (!self::isRestricted($event->getContext())) {
            return;
        }
        foreach (array_keys(PurchaseFieldGuard::FIELDS) as $entityName) {
            foreach ($event->getCommandsForEntity($entityName) as $command) {
                $this->guardCommand($entityName, $command);
            }
        }
    }

    /** @param list<string> $fields */
    private function checkCriteria(string $entityName, array $fields, Context $context): void
    {
        if ($fields !== [] && self::isRestricted($context) && PurchaseFieldGuard::touchesProtectedField($entityName, $fields)) {
            throw PurchaseAccessDeniedException::search($entityName);
        }
    }

    private function guardCommand(string $entityName, WriteCommand $command): void
    {
        $isInsert = $command instanceof InsertCommand;
        if (!$isInsert && !$command instanceof UpdateCommand) {
            return;
        }
        $payload = $command->getPayload();
        $old = null;

        foreach (PurchaseFieldGuard::FIELDS[$entityName] as $property => [$column, $kind]) {
            if (!\array_key_exists($column, $payload)) {
                continue;
            }
            if ($kind === 'scalar') {
                if ($isInsert) {
                    // Neuanlage ohne Recht (z. B. Kopie): EK wird nicht übernommen
                    $command->addPayload($column, null);
                    continue;
                }
                throw PurchaseAccessDeniedException::write($entityName, $property);
            }

            if (!$isInsert && $old === null) {
                $old = $this->loadStored($entityName, $command);
            }
            $new = \is_string($payload[$column]) ? json_decode($payload[$column], true) : $payload[$column];
            $stored = $old[$column] ?? null;
            $storedValue = \is_string($stored) ? json_decode($stored, true) : null;
            $merged = PurchaseFieldGuard::mergeJson($kind, $new, $storedValue, $old['tier_basis'] ?? null);
            $command->addPayload($column, $merged === null ? null : json_encode($merged, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR));
        }
    }

    /** @return array<string, mixed> gespeicherte JSON-Spalten (und tier_basis) des Datensatzes */
    private function loadStored(string $entityName, WriteCommand $command): array
    {
        $primaryKey = $command->getPrimaryKey();
        if (!isset($primaryKey['id'])) {
            return [];
        }
        $columns = [];
        foreach (PurchaseFieldGuard::FIELDS[$entityName] as [$column, $kind]) {
            if ($kind !== 'scalar') {
                $columns[] = '`' . $column . '`';
            }
        }
        if (\in_array($entityName, ['ld_mensura_material', 'ld_mensura_calculation'], true)) {
            $columns[] = '`tier_basis`';
        }
        if ($columns === []) {
            return [];
        }
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT %s FROM `%s` WHERE `id` = :id', implode(', ', $columns), $entityName),
            ['id' => $primaryKey['id']],
        );

        return $row === false ? [] : $row;
    }
}
