<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class StallRepository
{
    /** @return list<array<string,mixed>> */
    public static function elenco(int $limite = 100): array
    {
        return Database::seleziona(
            'SELECT e.*, s.name AS punto_vendita, s.city, c.name AS insegna
             FROM stall_events e
             JOIN stores s ON s.id = e.store_id
             JOIN chains c ON c.id = s.chain_id
             ORDER BY e.event_date DESC
             LIMIT ' . max(1, min($limite, 500))
        );
    }

    /** @return list<array<string,mixed>> */
    public static function resa(): array
    {
        return Database::seleziona(
            'SELECT * FROM v_resa_punti_vendita ORDER BY raccolto_medio DESC'
        );
    }

    /** Verifica dell'ipotesi del progetto: la promozione sposta davvero la raccolta? */
    public static function confrontoPromo(): array
    {
        return Database::selezionaUno(
            'SELECT
                COUNT(CASE WHEN promo_active = 1 THEN 1 END) AS banchetti_con_promo,
                COUNT(CASE WHEN promo_active = 0 THEN 1 END) AS banchetti_senza_promo,
                ROUND(AVG(CASE WHEN promo_active = 1 THEN donations_eur END), 2) AS medio_con_promo,
                ROUND(AVG(CASE WHEN promo_active = 0 THEN donations_eur END), 2) AS medio_senza_promo
             FROM stall_events
             WHERE donations_eur IS NOT NULL'
        ) ?? [];
    }

    /** @param array<string,mixed> $dati */
    public static function crea(array $dati): int
    {
        Database::esegui(
            'INSERT INTO stall_events
                (store_id, outreach_request_id, event_date, volunteers_count,
                 donations_eur, promo_active, footfall_rating, notes)
             VALUES (:store_id, :outreach_request_id, :event_date, :volunteers_count,
                     :donations_eur, :promo_active, :footfall_rating, :notes)',
            [
                'store_id' => (int) $dati['store_id'],
                'outreach_request_id' => ($dati['outreach_request_id'] ?? '') !== ''
                    ? (int) $dati['outreach_request_id'] : null,
                'event_date' => (string) $dati['event_date'],
                'volunteers_count' => ($dati['volunteers_count'] ?? '') !== ''
                    ? (int) $dati['volunteers_count'] : null,
                'donations_eur' => ($dati['donations_eur'] ?? '') !== ''
                    ? (float) str_replace(',', '.', (string) $dati['donations_eur']) : null,
                'promo_active' => isset($dati['promo_active']) ? 1 : 0,
                'footfall_rating' => ($dati['footfall_rating'] ?? '') !== ''
                    ? max(1, min(5, (int) $dati['footfall_rating'])) : null,
                'notes' => ($dati['notes'] ?? '') !== '' ? $dati['notes'] : null,
            ]
        );

        return (int) Database::pdo()->lastInsertId();
    }
}
