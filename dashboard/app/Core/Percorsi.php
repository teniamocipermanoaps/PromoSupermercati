<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Da dove pende l'applicazione dentro il dominio.
 *
 * Alla radice (https://esempio.it/) la base e' la stringa vuota e tutto resta
 * come prima. In una sottocartella (https://esempio.it/promosupermercati) la
 * base e' '/promosupermercati', e ogni percorso costruito dall'applicazione ci
 * passa davanti.
 *
 * Il valore si dichiara in APP_BASE_PATH, non si indovina: dedurlo da
 * SCRIPT_NAME funziona con certe configurazioni del server web e non con
 * altre, e il modo in cui fallisce e' un giro di redirect a vuoto.
 *
 * Dentro l'applicazione i percorsi restano quelli di sempre ('/campagne'):
 * il prefisso si aggiunge solo sul confine, quando si scrive un indirizzo in
 * una pagina o in un Location, e si toglie quando ne arriva uno dal browser.
 */
final class Percorsi
{
    private static ?string $base = null;

    /**
     * Il prefisso, senza barra finale. Stringa vuota se si sta alla radice.
     *
     * Il valore e' verificato qui contro un elenco chiuso di caratteri, quindi
     * puo' essere stampato nelle viste senza escaping: e' l'unico punto da cui
     * entra, e da qui non esce niente che non sia un percorso.
     */
    public static function base(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }

        $grezzo = trim((string) Config::get('APP_BASE_PATH', ''));
        $grezzo = trim($grezzo, '/');

        if ($grezzo === '') {
            return self::$base = '';
        }

        if (preg_match('#^[A-Za-z0-9._~-]+(/[A-Za-z0-9._~-]+)*$#', $grezzo) !== 1) {
            throw new RuntimeException(
                "APP_BASE_PATH non e' un percorso valido: '{$grezzo}'. "
                . "Esempio: APP_BASE_PATH=/promosupermercati"
            );
        }

        return self::$base = '/' . $grezzo;
    }

    /** Da percorso interno a indirizzo scrivibile in una pagina. */
    public static function a(string $percorso): string
    {
        $base = self::base();
        if ($percorso === '/') {
            // Alla radice deve restare '/', non la stringa vuota.
            return $base === '' ? '/' : $base;
        }
        return $base . $percorso;
    }

    /**
     * Da indirizzo ricevuto dal browser a percorso interno.
     *
     * Un indirizzo che non comincia con la base e' una richiesta fuori
     * dall'applicazione: si restituisce com'e', cosi' non corrisponde a nessuna
     * rotta e finisce in 404. Non si prova a indovinare.
     */
    public static function interno(string $uri): string
    {
        $percorso = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = self::base();

        if ($base !== '') {
            if ($percorso === $base) {
                return '/';
            }
            if (str_starts_with($percorso, $base . '/')) {
                $percorso = substr($percorso, strlen($base));
            }
        }

        return rtrim($percorso, '/') ?: '/';
    }

    /** Solo per i test: dimentica il valore gia' letto. */
    public static function dimentica(): void
    {
        self::$base = null;
    }
}
