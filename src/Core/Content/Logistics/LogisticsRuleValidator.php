<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Logistics;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Prüft Logistikregeln beim Schreiben (KONZEPT 3d.5): genau ein Ziel, gültige Werte für Kostenart, Form,
 * Schwellenbasis, Geltung des Mindestbetrags und Herkunft. Rampe/Schwelle/Mindestbetrag prüft zusätzlich die Datenbank.
 */
class LogisticsRuleValidator implements EventSubscriberInterface
{
    private const ENUMS = [
        'cost_type' => LogisticsRuleDefinition::COST_TYPES,
        'form' => LogisticsRuleDefinition::FORMS,
        'threshold_basis' => LogisticsRuleDefinition::THRESHOLD_BASES,
        'minimum_scope' => LogisticsRuleDefinition::MINIMUM_SCOPES,
        'source' => LogisticsRuleDefinition::SOURCES,
    ];

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'validate'];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command->getDefinition()->getEntityName() !== LogisticsRuleDefinition::ENTITY_NAME) {
                continue;
            }
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }
            $payload = $command->getPayload();
            $violations = new ConstraintViolationList();

            foreach (self::ENUMS as $column => $allowed) {
                if (!\array_key_exists($column, $payload) || $payload[$column] === null) {
                    continue;
                }
                if (!\in_array($payload[$column], $allowed, true)) {
                    $violations->add($this->violation(
                        \sprintf('Ungültiger Wert „%s“ für %s. Erlaubt: %s.', (string) $payload[$column], $column, implode(', ', $allowed)),
                        '/' . $column,
                    ));
                }
            }

            if ($command instanceof InsertCommand) {
                $calculation = ($payload['calculation_id'] ?? null) !== null;
                $material = ($payload['material_id'] ?? null) !== null;
                $optionValue = ($payload['option_value_id'] ?? null) !== null;
                $valid = ($material && !$optionValue && !$calculation)
                    || ($optionValue && !$material)
                    || ($calculation && !$material && !$optionValue);
                if (!$valid) {
                    $violations->add($this->violation(
                        'Ein Logistikanteil hängt an genau einem Ziel: Kalkulation, Material oder Optionswert (Optionswert optional mit Kalkulation).',
                        '/materialId',
                    ));
                }
            }

            if ($violations->count() > 0) {
                $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
            }
        }
    }

    private function violation(string $message, string $path): ConstraintViolation
    {
        return new ConstraintViolation($message, $message, [], null, $path, null);
    }
}
