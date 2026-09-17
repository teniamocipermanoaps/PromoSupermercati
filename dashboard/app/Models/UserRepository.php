<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateTimeImmutable;

/**
 * Utenti della dashboard.
 *
 * L'utente applicativo del database ha solo SELECT, INSERT, UPDATE: qui non
 * si cancella niente, si disattiva.
 */
final class UserRepository
{
    /** Tentativi falliti consecutivi tollerati prima del blocco. */
    public const TENTATIVI_MASSIMI = 5;

    /** Durata del blocco, in minuti. */
    public const MINUTI_BLOCCO = 15;

    /** @return array<string,mixed>|null */
    public static function trovaPerEmail(string $email): ?array
    {
        return Database::selezionaUno(
            'SELECT id, email, full_name, password_hash, role, is_active,
                    failed_attempts, locked_until
             FROM users WHERE email = :email',
            ['email' => self::normalizzaEmail($email)]
        );
    }

    /** @param array<string,mixed> $utente */
    public static function bloccato(array $utente): bool
    {
        $fino = $utente['locked_until'] ?? null;
        if (!is_string($fino) || $fino === '') {
            return false;
        }
        return new DateTimeImmutable($fino) > new DateTimeImmutable();
    }

    /** Accesso riuscito: azzera i tentativi e toglie l'eventuale blocco. */
    public static function registraAccesso(int $id): void
    {
        Database::esegui(
            'UPDATE users
                SET last_login_at = NOW(), failed_attempts = 0, locked_until = NULL
              WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * Accesso fallito: conta il tentativo e, superata la soglia, blocca.
     *
     * Il conteggio si incrementa nella stessa istruzione che decide il blocco,
     * cosi' due tentativi in parallelo non si sovrascrivono a vicenda.
     *
     * L'ordine delle due assegnazioni conta: MariaDB le valuta da sinistra a
     * destra, e la seconda vedrebbe il valore gia' incrementato dalla prima.
     * Con locked_until davanti, la condizione legge il contatore vecchio e il
     * blocco scatta al quinto tentativo, non al quarto.
     */
    public static function registraFallimento(int $id): void
    {
        $bloccoFino = (new DateTimeImmutable())
            ->modify('+' . self::MINUTI_BLOCCO . ' minutes')
            ->format('Y-m-d H:i:s');

        Database::esegui(
            'UPDATE users
                SET locked_until = IF(failed_attempts + 1 >= :massimo, :bloccoFino, locked_until),
                    failed_attempts = failed_attempts + 1
              WHERE id = :id',
            ['massimo' => self::TENTATIVI_MASSIMI, 'bloccoFino' => $bloccoFino, 'id' => $id]
        );
    }

    /** Riscrive l'hash quando l'algoritmo predefinito di PHP cambia. */
    public static function aggiornaHash(int $id, string $hash): void
    {
        Database::esegui(
            'UPDATE users SET password_hash = :hash WHERE id = :id',
            ['hash' => $hash, 'id' => $id]
        );
    }

    /** Crea un accesso. Usata da ops/scripts/crea_utente.php. */
    public static function crea(string $email, string $nome, string $hash, string $ruolo): int
    {
        Database::esegui(
            'INSERT INTO users (email, full_name, password_hash, role)
             VALUES (:email, :nome, :hash, :ruolo)',
            ['email' => self::normalizzaEmail($email), 'nome' => $nome, 'hash' => $hash, 'ruolo' => $ruolo]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * Reimposta la password di un accesso esistente, lo riattiva e sblocca.
     * E' la via di recupero: non esiste un "password dimenticata" via email.
     */
    public static function reimpostaPassword(int $id, string $hash): void
    {
        Database::esegui(
            'UPDATE users
                SET password_hash = :hash, is_active = 1,
                    failed_attempts = 0, locked_until = NULL
              WHERE id = :id',
            ['hash' => $hash, 'id' => $id]
        );
    }

    private static function normalizzaEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
