<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Cart;

use Shopware\Core\Checkout\Cart\Error\Error;

/**
 * Konfigurierte Position ist nicht (mehr) gültig, z. B. weil sich Stammdaten geändert haben – sie wurde aus dem
 * Warenkorb entfernt. Snippet-Schlüssel im Shop: `checkout.ld-mensura-configuration-invalid` (Parameter `name`, `reason`).
 */
class MensuraCartError extends Error
{
    public function __construct(
        private readonly string $lineItemId,
        private readonly string $name,
        private readonly string $reason,
    ) {
        parent::__construct($reason);
    }

    public function getId(): string
    {
        return 'ld-mensura-configuration-invalid-' . $this->lineItemId;
    }

    public function getMessageKey(): string
    {
        return 'ld-mensura-configuration-invalid';
    }

    public function getLevel(): int
    {
        return self::LEVEL_WARNING;
    }

    public function blockOrder(): bool
    {
        return false;
    }

    public function getParameters(): array
    {
        return ['name' => $this->name, 'reason' => $this->reason];
    }
}
