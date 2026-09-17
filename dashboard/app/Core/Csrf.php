<?php

declare(strict_types=1);

namespace App\Core;

/** Protezione delle POST: ogni form porta un token legato alla sessione. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verifica(?string $inviato): bool
    {
        return is_string($inviato)
            && !empty($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $inviato);
    }

    public static function campo(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }
}
