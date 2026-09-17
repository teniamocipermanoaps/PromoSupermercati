<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Unico punto di accesso al database.
 *
 * Tutto passa da qui e sempre con statement preparati: se un giorno si
 * cambiasse impianto, questo e' il solo file da riscrivere.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Config::get('DB_HOST', '127.0.0.1');
        $porta = Config::get('DB_PORT', '3306');
        $nome = Config::get('DB_NAME', 'osservatorio_promo');
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $porta, $nome);

        try {
            self::$pdo = new PDO(
                $dsn,
                Config::get('DB_USER', 'root'),
                Config::get('DB_PASSWORD', ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Connessione al database non riuscita: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    /** @param array<string,mixed> $parametri */
    public static function seleziona(string $sql, array $parametri = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($parametri);
        return $stmt->fetchAll();
    }

    /** @param array<string,mixed> $parametri */
    public static function selezionaUno(string $sql, array $parametri = []): ?array
    {
        $righe = self::seleziona($sql, $parametri);
        return $righe[0] ?? null;
    }

    /** @param array<string,mixed> $parametri */
    public static function esegui(string $sql, array $parametri = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($parametri);
        return $stmt->rowCount();
    }
}
