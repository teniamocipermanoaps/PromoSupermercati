<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Campagne promozionali.
 *
 * L'inserimento manuale e' la strada principale finche' il crawler non e'
 * attivo: bastano insegna, titolo e le due date perche' l'agenda funzioni.
 */
final class CampaignRepository
{
    /** @return list<array<string,mixed>> */
    public static function elenco(): array
    {
        return Database::seleziona(
            'SELECT f.id, f.title, f.valid_from, f.valid_to, f.source_kind,
                    c.name AS insegna, c.slug AS chain_slug,
                    COUNT(fs.store_id) AS punti_vendita,
                    DATEDIFF(f.valid_to, f.valid_from) + 1 AS durata
             FROM flyers f
             JOIN chains c ON c.id = f.chain_id
             LEFT JOIN flyer_stores fs ON fs.flyer_id = f.id
             GROUP BY f.id, f.title, f.valid_from, f.valid_to, f.source_kind, c.name, c.slug
             ORDER BY f.valid_from DESC'
        );
    }

    /** @return list<array<string,mixed>> */
    public static function catene(): array
    {
        return Database::seleziona('SELECT id, slug, name FROM chains ORDER BY name');
    }

    /**
     * Crea una campagna e la collega ai punti vendita indicati.
     *
     * @param list<int> $storeIds elenco vuoto = tutti i PDV della catena
     */
    public static function crea(int $chainId, string $titolo, string $dal, string $al, array $storeIds = []): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            // L'hash deduplica anche gli inserimenti manuali ripetuti per errore.
            $impronta = hash('sha256', $chainId . '|' . $titolo . '|' . $dal . '|' . $al);

            Database::esegui(
                'INSERT INTO flyers (chain_id, source_kind, title, valid_from, valid_to,
                                     source_url, file_type, file_hash, downloaded_at, status)
                 VALUES (:chain_id, "structured", :titolo, :dal, :al, "inserimento manuale",
                         "json", :hash, NOW(), "extracted")',
                ['chain_id' => $chainId, 'titolo' => $titolo, 'dal' => $dal, 'al' => $al, 'hash' => $impronta]
            );
            $flyerId = (int) $pdo->lastInsertId();

            if ($storeIds === []) {
                Database::esegui(
                    'INSERT INTO flyer_stores (flyer_id, store_id)
                     SELECT :flyer_id, id FROM stores WHERE chain_id = :chain_id AND is_active = 1',
                    ['flyer_id' => $flyerId, 'chain_id' => $chainId]
                );
            } else {
                foreach ($storeIds as $storeId) {
                    Database::esegui(
                        'INSERT IGNORE INTO flyer_stores (flyer_id, store_id) VALUES (:f, :s)',
                        ['f' => $flyerId, 's' => (int) $storeId]
                    );
                }
            }

            $pdo->commit();
            return $flyerId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
