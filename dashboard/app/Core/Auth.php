<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sessione di accesso delle volontarie.
 *
 * In sessione sta il minimo per sapere chi sta lavorando: identificativo,
 * nome, email e ruolo. Tutto il resto si rilegge dal database quando serve,
 * cosi' una revoca o un cambio di nome non restano congelati in un cookie.
 *
 * Le sessioni sono file di PHP e non righe di tabella: l'utente applicativo
 * del database non ha il permesso di cancellare, e una tabella di sessioni
 * senza DELETE crescerebbe senza fine.
 */
final class Auth
{
    /** Dopo otto ore di inattivita' si rientra: un telefono si presta o si perde. */
    private const INATTIVITA_MASSIMA = 8 * 3600;

    private const CHIAVE_UTENTE = 'utente';
    private const CHIAVE_ATTIVITA = 'ultima_attivita';

    /** Avvia la sessione con i parametri del cookie gia' irrobustiti. */
    public static function avviaSessione(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Niente identificativi di sessione inventati da fuori e niente id
        // nell'indirizzo: solo cookie, e solo id generati da noi.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_set_cookie_params([
            'lifetime' => 0,
            // Limitato alla sottocartella: in una sottocartella un cookie con
            // path '/' verrebbe inviato a ogni altra applicazione dello stesso
            // dominio, e quelle potrebbero sovrascriverlo.
            'path' => Percorsi::a('/'),
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => self::connessioneCifrata(),
        ]);

        session_start();
        self::scadiSeInattiva();
    }

    public static function autenticata(): bool
    {
        return isset($_SESSION[self::CHIAVE_UTENTE]['id']);
    }

    /** @return array<string,mixed>|null */
    public static function utente(): ?array
    {
        return $_SESSION[self::CHIAVE_UTENTE] ?? null;
    }

    /**
     * Apre la sessione per un utente gia' verificato.
     *
     * @param array<string,mixed> $riga riga della tabella users
     */
    public static function accedi(array $riga): void
    {
        // Identificativo nuovo: se qualcuno avesse fatto fissare il precedente,
        // da qui in avanti non vale piu' nulla.
        session_regenerate_id(true);

        // Anche il token CSRF riparte: era legato alla sessione anonima.
        unset($_SESSION['csrf']);

        $_SESSION[self::CHIAVE_UTENTE] = [
            'id' => (int) $riga['id'],
            'nome' => (string) $riga['full_name'],
            'email' => (string) $riga['email'],
            'ruolo' => (string) $riga['role'],
        ];
        $_SESSION[self::CHIAVE_ATTIVITA] = time();
    }

    /** Chiude la sessione e cancella il cookie dal browser. */
    public static function esci(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parametri = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parametri['path'],
                'domain' => $parametri['domain'],
                'secure' => $parametri['secure'],
                'httponly' => $parametri['httponly'],
                'samesite' => $parametri['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    /**
     * Percorso interno sicuro per il ritorno dopo l'accesso.
     *
     * Accetta solo percorsi assoluti di questo sito: senza questo controllo
     * ?ritorno=//altro-sito trasformerebbe la pagina di accesso in un trampolino
     * verso una copia della dashboard fatta per rubare le password.
     */
    public static function percorsoSicuro(?string $percorso): string
    {
        if (!is_string($percorso) || $percorso === '' || $percorso === '/accesso') {
            return '/';
        }
        if ($percorso[0] !== '/' || str_starts_with($percorso, '//')) {
            return '/';
        }
        // Un percorso vero non contiene schema, a capo o spazi.
        if (preg_match('#[\r\n\s\\\\]|^/[^/]*:#', $percorso) === 1) {
            return '/';
        }
        return $percorso;
    }

    private static function scadiSeInattiva(): void
    {
        if (!self::autenticata()) {
            return;
        }

        $ultima = (int) ($_SESSION[self::CHIAVE_ATTIVITA] ?? 0);
        if ($ultima > 0 && time() - $ultima > self::INATTIVITA_MASSIMA) {
            self::esci();
            session_start();
            return;
        }

        $_SESSION[self::CHIAVE_ATTIVITA] = time();
    }

    private static function connessioneCifrata(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? 'off') !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        // Dietro un proxy che termina TLS il protocollo originale arriva di qui.
        return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
