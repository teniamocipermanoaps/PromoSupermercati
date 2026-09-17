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

    /** @return list<array<string,mixed>> */
    public static function elenco(): array
    {
        return Database::seleziona(
            'SELECT s.id, s.name, s.city, s.province, c.name AS insegna,
                    COALESCE(b.format, "supermercato") AS tipologia
             FROM stores s
             JOIN chains c ON c.id = s.chain_id
             LEFT JOIN store_banners b ON b.id = s.banner_id
             WHERE s.is_active = 1
             ORDER BY s.province, s.city, s.name'
        );
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
