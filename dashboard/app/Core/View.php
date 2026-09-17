<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Rendering delle viste dentro il layout comune. */
final class View
{
    public static string $cartella = __DIR__ . '/../Views';

    /** @param array<string,mixed> $dati */
    public static function rendi(string $vista, array $dati = []): void
    {
        $file = self::$cartella . '/' . $vista . '.php';
        if (!is_readable($file)) {
            throw new RuntimeException("Vista non trovata: {$vista}");
        }

        extract($dati, EXTR_SKIP);
        ob_start();
        require $file;
        $contenuto = ob_get_clean();

        require self::$cartella . '/layout.php';
    }

    /** Scorciatoia per l'escaping: usata ovunque nelle viste. */
    public static function e(mixed $valore): string
    {
        return htmlspecialchars((string) ($valore ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
