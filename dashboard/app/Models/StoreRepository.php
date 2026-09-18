<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class StoreRepository
{
    public static function trova(int $id): ?array
    {
        return Database::selezionaUno(
            'SELECT s.*, c.name AS insegna, c.slug AS chain_slug, c.segment,
                    b.name AS banner, COALESCE(b.format, "supermercato") AS tipologia
             FROM stores s
             JOIN chains c ON c.id = s.chain_id
             LEFT JOIN store_banners b ON b.id = s.banner_id
             WHERE s.id = :id',
            ['id' => $id]
        );
    }

    /** Quanti punti vendita mostrare prima di chiedere di filtrare. */
    public const LIMITE_ELENCO = 300;

    /**
     * Elenco filtrabile.
     *
     * L'indirizzo fa parte delle colonne mostrate, non e' un dettaglio della
     * scheda: in una citta' ci sono dieci Conad, e senza l'indirizzo bisogna
     * aprirli uno per uno per capire quale sia quello sotto casa.
     *
     * @param array<string,string> $filtri
     * @return list<array<string,mixed>>
     */
    public static function elenco(array $filtri = []): array
    {
        [$dove, $parametri] = self::condizioni($filtri);

        return Database::seleziona(
            'SELECT s.id, s.name, s.address, s.postal_code, s.city, s.province,
                    s.phone, c.name AS insegna,
                    COALESCE(b.name, "—") AS banner,
                    COALESCE(b.format, "supermercato") AS tipologia
             FROM stores s
             JOIN chains c ON c.id = s.chain_id
             LEFT JOIN store_banners b ON b.id = s.banner_id
             WHERE ' . $dove . '
             ORDER BY s.province, s.city, c.name, s.name
             LIMIT ' . self::LIMITE_ELENCO,
            $parametri
        );
    }

    /** @param array<string,string> $filtri */
    public static function conta(array $filtri = []): int
    {
        [$dove, $parametri] = self::condizioni($filtri);
        $riga = Database::selezionaUno(
            'SELECT COUNT(*) AS quanti
             FROM stores s
             JOIN chains c ON c.id = s.chain_id
             LEFT JOIN store_banners b ON b.id = s.banner_id
             WHERE ' . $dove,
            $parametri
        );
        return (int) ($riga['quanti'] ?? 0);
    }

    /**
     * Condizioni e parametri dei filtri.
     *
     * I nomi di colonna sono scritti qui a mano e non arrivano mai dalla
     * richiesta: dalla richiesta arrivano solo i valori, e sempre legati.
     *
     * @param array<string,string> $filtri
     * @return array{0:string, 1:array<string,mixed>}
     */
    private static function condizioni(array $filtri): array
    {
        $dove = ['s.is_active = 1'];
        $parametri = [];

        if (!empty($filtri['citta'])) {
            $dove[] = 's.city = :citta';
            $parametri['citta'] = $filtri['citta'];
        }
        if (!empty($filtri['provincia'])) {
            $dove[] = 's.province = :provincia';
            $parametri['provincia'] = $filtri['provincia'];
        }
        if (!empty($filtri['catena'])) {
            $dove[] = 'c.slug = :catena';
            $parametri['catena'] = $filtri['catena'];
        }
        if (!empty($filtri['tipologia'])) {
            $dove[] = 'COALESCE(b.format, "supermercato") = :tipologia';
            $parametri['tipologia'] = $filtri['tipologia'];
        }
        if (!empty($filtri['cerca'])) {
            // Cerca nel nome e nell'indirizzo: "Vomero" trova il negozio anche
            // quando il nome e' solo "Conad".
            $dove[] = '(s.name LIKE :cerca OR s.address LIKE :cerca2)';
            $parametri['cerca'] = '%' . $filtri['cerca'] . '%';
            $parametri['cerca2'] = '%' . $filtri['cerca'] . '%';
        }

        return [implode(' AND ', $dove), $parametri];
    }

    /** @return array<string,list<string>> valori disponibili per i filtri */
    public static function valoriFiltri(): array
    {
        $estrai = static fn (string $sql): array => array_column(Database::seleziona($sql), 'v');

        return [
            'provincia' => $estrai('SELECT DISTINCT province AS v FROM stores WHERE is_active = 1 ORDER BY v'),
            'citta'     => $estrai('SELECT DISTINCT city AS v FROM stores WHERE is_active = 1 ORDER BY v'),
            'catena'    => $estrai('SELECT DISTINCT c.slug AS v FROM chains c
                                    JOIN stores s ON s.chain_id = c.id AND s.is_active = 1 ORDER BY v'),
            'tipologia' => $estrai('SELECT DISTINCT COALESCE(b.format, "supermercato") AS v
                                    FROM stores s LEFT JOIN store_banners b ON b.id = s.banner_id
                                    WHERE s.is_active = 1 ORDER BY v'),
        ];
    }

    /**
     * Aggiorna i campi che curano le segretarie.
     *
     * Nome, indirizzo, CAP e coordinate NON si toccano da qui: arrivano da
     * OpenStreetMap e l'importatore li riscrive a ogni passaggio, quindi una
     * modifica fatta a mano sparirebbe al primo aggiornamento senza che
     * nessuno capisca perche'. Questi campi invece l'importatore non li
     * sovrascrive mai.
     *
     * @param array<string,mixed> $dati
     */
    public static function aggiorna(int $id, array $dati): void
    {
        Database::esegui(
            'UPDATE stores SET
                phone = :phone, email = :email, opening_hours = :orari,
                has_parking = :parcheggio, outdoor_space_notes = :spazio
             WHERE id = :id',
            [
                'phone' => self::vuotoInNull($dati['phone'] ?? null),
                'email' => self::vuotoInNull($dati['email'] ?? null),
                'orari' => self::vuotoInNull($dati['opening_hours'] ?? null),
                'parcheggio' => ($dati['has_parking'] ?? '') === '' ? null : (int) $dati['has_parking'],
                'spazio' => self::vuotoInNull($dati['outdoor_space_notes'] ?? null),
                'id' => $id,
            ]
        );
    }

    /**
     * Registra un referente.
     *
     * DATI PERSONALI: solo quello che serve a gestire il rapporto con il punto
     * vendita. Si disattivano quando il rapporto si chiude.
     *
     * @param array<string,mixed> $dati
     */
    public static function creaContatto(int $storeId, array $dati): void
    {
        Database::esegui(
            'INSERT INTO store_contacts
                (store_id, full_name, role, phone, email, preferred_channel, notes)
             VALUES (:store, :nome, :ruolo, :phone, :email, :canale, :note)',
            [
                'store' => $storeId,
                'nome' => trim((string) ($dati['full_name'] ?? '')),
                'ruolo' => self::vuotoInNull($dati['role'] ?? null),
                'phone' => self::vuotoInNull($dati['phone'] ?? null),
                'email' => self::vuotoInNull($dati['email'] ?? null),
                'canale' => in_array($dati['preferred_channel'] ?? '', self::CANALI, true)
                    ? $dati['preferred_channel'] : null,
                'note' => self::vuotoInNull($dati['notes'] ?? null),
            ]
        );
    }

    /** Un referente che non serve piu' si disattiva: non si cancella. */
    public static function disattivaContatto(int $contattoId, int $storeId): void
    {
        Database::esegui(
            'UPDATE store_contacts SET is_active = 0 WHERE id = :id AND store_id = :store',
            ['id' => $contattoId, 'store' => $storeId]
        );
    }

    public const CANALI = ['telefono', 'email', 'di_persona', 'pec'];

    private static function vuotoInNull(?string $valore): ?string
    {
        $valore = trim((string) $valore);
        return $valore === '' ? null : $valore;
    }

    /** @return list<array<string,mixed>> */
    public static function contatti(int $storeId): array
    {
        return Database::seleziona(
            'SELECT * FROM store_contacts WHERE store_id = :id AND is_active = 1 ORDER BY id',
            ['id' => $storeId]
        );
    }

    /** @return list<array<string,mixed>> campagne presenti e future del punto vendita */
    public static function campagne(int $storeId): array
    {
        return Database::seleziona(
            'SELECT f.id, f.title AS campagna, f.valid_from AS inizio, f.valid_to AS fine
             FROM flyers f
             JOIN flyer_stores fs ON fs.flyer_id = f.id
             WHERE fs.store_id = :id AND f.valid_to >= CURRENT_DATE
             ORDER BY f.valid_from',
            ['id' => $storeId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function richieste(int $storeId): array
    {
        return Database::seleziona(
            'SELECT r.*, f.title AS campagna
             FROM outreach_requests r
             LEFT JOIN flyers f ON f.id = r.flyer_id
             WHERE r.store_id = :id
             ORDER BY r.created_at DESC',
            ['id' => $storeId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function banchetti(int $storeId): array
    {
        return Database::seleziona(
            'SELECT * FROM stall_events WHERE store_id = :id ORDER BY event_date DESC',
            ['id' => $storeId]
        );
    }

    public static function resa(int $storeId): ?array
    {
        return Database::selezionaUno(
            'SELECT * FROM v_resa_punti_vendita WHERE store_id = :id',
            ['id' => $storeId]
        );
    }
}
