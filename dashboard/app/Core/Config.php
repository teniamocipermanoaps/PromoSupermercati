<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Configurazione da variabili d'ambiente, con .env come comodita' di sviluppo.
 * Le credenziali non stanno mai nel codice.
 */
final class Config
{
    private static array $valori = [];

    public static function carica(string $percorsoEnv): void
    {
        if (!is_readable($percorsoEnv)) {
            return;
        }
        foreach (file($percorsoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $riga) {
            $riga = trim($riga);
            if ($riga === '' || str_starts_with($riga, '#') || !str_contains($riga, '=')) {
                continue;
            }
            [$chiave, $valore] = explode('=', $riga, 2);
            self::$valori[trim($chiave)] = trim($valore, " \t\"'");
        }
    }

    public static function get(string $chiave, ?string $predefinito = null): ?string
    {
        $daAmbiente = getenv($chiave);
        if ($daAmbiente !== false && $daAmbiente !== '') {
            return $daAmbiente;
        }
        return self::$valori[$chiave] ?? $predefinito;
    }
}
