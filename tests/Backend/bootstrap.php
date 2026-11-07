<?php

declare(strict_types=1);

/*
 * Bootstrap der Backend-Tests ohne Shopware: Die Preis-Engine (src/Service/Calculation) und die Demo-Daten
 * (src/Service/Demo) sind reines PHP. Einfacher PSR-4-Autoloader für Plugin und Tests.
 */
spl_autoload_register(static function (string $class): void {
    $map = [
        'Lohndruckerei\\Mensura\\Tests\\Backend\\' => __DIR__ . '/',
        'Lohndruckerei\\Mensura\\' => \dirname(__DIR__, 2) . '/src/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
