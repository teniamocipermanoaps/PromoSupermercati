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
                    GROUP_CONCAT(DISTINCT s.province ORDER BY s.province SEPARATOR \' \') AS province,
                    DATEDIFF(f.valid_to, f.valid_from) + 1 AS durata
             FROM flyers f
             JOIN chains c ON c.id = f.chain_id
             LEFT JOIN flyer_stores fs ON fs.flyer_id = f.id
             LEFT JOIN stores s ON s.id = fs.store_id
             GROUP BY f.id, f.title, f.valid_from, f.valid_to, f.source_kind, c.name, c.slug
             ORDER BY f.valid_from DESC'
        );
    }

    /** Quanti punti vendita ha agganciato una campagna. */
    public static function puntiCollegati(int $flyerId): int
    {
        $riga = Database::selezionaUno(
            'SELECT COUNT(*) AS quanti FROM flyer_stores WHERE flyer_id = :id',
            ['id' => $flyerId]
        );
        return (int) ($riga['quanti'] ?? 0);
    }

    /**
     * Sigle di provincia dove ci sono punti vendita attivi, con quanti sono:
     * senza il conteggio il modulo non fa capire che scegliendo una provincia
     * senza negozi la campagna nasce scollegata.
     *
     * @return list<array{sigla:string,quanti:int}>
     */
    public static function province(): array
    {
        $righe = Database::seleziona(
            'SELECT province AS sigla, COUNT(*) AS quanti
               FROM stores
              WHERE is_active = 1 AND province IS NOT NULL AND province <> \'\'
              GROUP BY province
              ORDER BY province'
        );

        return array_map(
            static fn (array $riga): array => [
                'sigla' => (string) $riga['sigla'],
                'quanti' => (int) $riga['quanti'],
            ],
            $righe
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
     * @param list<int>    $storeIds elenco vuoto = tutti i PDV della catena
     * @param list<string>  $province elenco vuoto = tutta Italia
     */
    public static function crea(
        int $chainId,
        string $titolo,
        string $dal,
        string $al,
        array $storeIds = [],
        array $province = []
    ): int
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
                // Una campagna non vale sempre per tutta l'insegna: Conad e' una
                // federazione di cooperative regionali che fanno volantini con
                // date diverse, quindi lo stesso "Sottocosto" a Bari e a Torino
                // cade in settimane differenti. Senza il filtro, l'agenda
                // proporrebbe a una segretaria di Torino giorni che valgono per
                // Bari.
                $sql = 'INSERT INTO flyer_stores (flyer_id, store_id)
                        SELECT :flyer_id, id FROM stores
                        WHERE chain_id = :chain_id AND is_active = 1';
                $parametri = ['flyer_id' => $flyerId, 'chain_id' => $chainId];

                if ($province !== []) {
                    $segnaposti = [];
                    foreach (array_values($province) as $indice => $sigla) {
                        $chiave = 'prov' . $indice;
                        $segnaposti[] = ':' . $chiave;
                        $parametri[$chiave] = $sigla;
                    }
                    $sql .= ' AND province IN (' . implode(', ', $segnaposti) . ')';
                }

                Database::esegui($sql, $parametri);
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
