<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Security;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zugriff auf Einkaufspreise ohne Recht `ld_mensura.purchase` (KONZEPT 3b) – HTTP 403.
 */
class PurchaseAccessDeniedException extends ShopwareHttpException
{
    public static function write(string $entity, string $field): self
    {
        return new self(
            'Einkaufspreise dürfen nur mit dem Recht „{{ privilege }}“ geändert werden ({{ entity }}.{{ field }}).',
            ['privilege' => PurchaseFieldGuard::PRIVILEGE, 'entity' => $entity, 'field' => $field],
        );
    }

    public static function search(string $entity): self
    {
        return new self(
            'Filtern, Sortieren und Auswerten nach Einkaufspreisen nur mit dem Recht „{{ privilege }}“ ({{ entity }}).',
            ['privilege' => PurchaseFieldGuard::PRIVILEGE, 'entity' => $entity],
        );
    }

    public function getErrorCode(): string
    {
        return 'LD_MENSURA__PURCHASE_ACCESS_DENIED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
