<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

/**
 * Liefertermin aus Werktagen: Nach dem Annahmeschluss oder an einem freien Tag beginnt die Produktion am
 * nächsten Arbeitstag; dann zählen `days` Arbeitstage (Feiertage aus der Plugin-Einstellung werden übersprungen).
 * Beispiel (Mo–Fr, 11:30): Bestellung Freitag 10:00, 5 Werktage → Freitag der Folgewoche.
 */
final class DeliveryCalendar
{
    public function __construct(private readonly CalculationContext $context)
    {
    }

    public function dateFor(int $days): \DateTimeImmutable
    {
        $now = $this->context->now;
        $day = $now->setTime(0, 0);
        $start = $day;
        if (!$this->isWorkingDay($day) || $this->afterCutoff($now)) {
            $start = $this->nextWorkingDay($day);
        }
        $date = $start;
        for ($i = 0; $i < $days; ++$i) {
            $date = $this->nextWorkingDay($date);
        }

        return $date;
    }

    private function afterCutoff(\DateTimeImmutable $now): bool
    {
        $cutoff = $this->context->orderCutoff;
        if ($cutoff === null || !preg_match('/^([0-2]\d):([0-5]\d)$/', $cutoff, $m)) {
            return false;
        }

        return (int) $now->format('H') * 60 + (int) $now->format('i') > (int) $m[1] * 60 + (int) $m[2];
    }

    private function nextWorkingDay(\DateTimeImmutable $day): \DateTimeImmutable
    {
        for ($i = 0; $i < 366; ++$i) {
            $day = $day->modify('+1 day');
            if ($this->isWorkingDay($day)) {
                return $day;
            }
        }

        return $day;
    }

    private function isWorkingDay(\DateTimeImmutable $day): bool
    {
        $workingDays = $this->context->workingDays === [] ? [1, 2, 3, 4, 5] : $this->context->workingDays;

        return \in_array((int) $day->format('N'), $workingDays, true)
            && !\in_array($day->format('Y-m-d'), $this->context->holidays, true);
    }
}
