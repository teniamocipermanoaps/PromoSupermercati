<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class OutreachRepository
{
    public const STATI = ['da_contattare', 'contattato', 'in_attesa', 'autorizzato',
        'rifiutato', 'rimandato', 'annullato'];
    public const CANALI = ['telefono', 'email', 'di_persona', 'pec'];

    /** @param array<string,mixed> $dati */
    public static function crea(array $dati): int
    {
        Database::esegui(
            'INSERT INTO outreach_requests
                (store_id, flyer_id, requested_by, target_date_from, target_date_to,
                 channel, status, next_follow_up, notes)
             VALUES (:store_id, :flyer_id, :requested_by, :dal, :al, :channel, :status,
                     :next_follow_up, :notes)',
            [
                'store_id' => (int) ($dati['store_id'] ?? 0),
                // Un campo assente o vuoto deve restare NULL: uno zero violerebbe
                // la chiave esterna verso flyers.
                'flyer_id' => ((int) ($dati['flyer_id'] ?? 0)) ?: null,
                'requested_by' => trim((string) ($dati['requested_by'] ?? '')),
                'dal' => (string) ($dati['target_date_from'] ?? ''),
                'al' => (string) ($dati['target_date_to'] ?? ''),
                'channel' => in_array($dati['channel'] ?? '', self::CANALI, true) ? $dati['channel'] : 'telefono',
                'status' => in_array($dati['status'] ?? '', self::STATI, true) ? $dati['status'] : 'da_contattare',
                'next_follow_up' => ($dati['next_follow_up'] ?? '') !== '' ? $dati['next_follow_up'] : null,
                'notes' => ($dati['notes'] ?? '') !== '' ? $dati['notes'] : null,
            ]
        );

        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $dati */
    public static function aggiorna(int $id, array $dati): void
    {
        $stato = in_array($dati['status'] ?? '', self::STATI, true) ? $dati['status'] : null;
        if ($stato === null) {
            return;
        }

        Database::esegui(
            'UPDATE outreach_requests SET
                status = :status,
                contacted_at = CASE WHEN contacted_at IS NULL AND :status2 <> "da_contattare"
                                    THEN NOW() ELSE contacted_at END,
                response_at = CASE WHEN :status3 IN ("autorizzato", "rifiutato")
                                   THEN NOW() ELSE response_at END,
                authorized_date = :authorized_date,
                next_follow_up  = :next_follow_up,
                refusal_reason  = :refusal_reason,
                notes = :notes
             WHERE id = :id',
            [
                'status' => $stato,
                'status2' => $stato,
                'status3' => $stato,
                'authorized_date' => ($dati['authorized_date'] ?? '') !== '' ? $dati['authorized_date'] : null,
                'next_follow_up' => ($dati['next_follow_up'] ?? '') !== '' ? $dati['next_follow_up'] : null,
                'refusal_reason' => ($dati['refusal_reason'] ?? '') !== '' ? $dati['refusal_reason'] : null,
                'notes' => ($dati['notes'] ?? '') !== '' ? $dati['notes'] : null,
                'id' => $id,
            ]
        );
    }

    public static function trova(int $id): ?array
    {
        return Database::selezionaUno('SELECT * FROM outreach_requests WHERE id = :id', ['id' => $id]);
    }
}
