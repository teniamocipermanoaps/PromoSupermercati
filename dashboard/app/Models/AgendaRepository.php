<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** L'agenda: campagne in arrivo con accanto lo stato del contatto. */
final class AgendaRepository
{
    /** Filtri ammessi: nome del filtro => colonna della vista. */
    private const FILTRI = [
        'citta'     => 'city',
        'provincia' => 'province',
        'catena'    => 'chain_slug',
        'tipologia' => 'tipologia',
        'stato'     => 'stato_richiesta',
    ];

    /**
     * @param array<string,string> $filtri
     * @return list<array<string,mixed>>
     */
    public static function prossime(array $filtri = []): array
    {
        $condizioni = [];
        $parametri = [];

        foreach (self::FILTRI as $nome => $colonna) {
            $valore = trim((string) ($filtri[$nome] ?? ''));
            if ($valore !== '') {
                // La colonna arriva da una whitelist, il valore e' sempre legato.
                $condizioni[] = "{$colonna} = :{$nome}";
                $parametri[$nome] = $valore;
            }
        }

        $where = $condizioni === [] ? '' : ' WHERE ' . implode(' AND ', $condizioni);

        return Database::seleziona(
            'SELECT * FROM v_agenda_contatti' . $where . ' ORDER BY inizio, punto_vendita',
            $parametri
        );
    }

    /** @return list<array<string,mixed>> */
    public static function daRichiamare(): array
    {
        return Database::seleziona(
            'SELECT r.id, r.next_follow_up, r.requested_by, r.status,
                    s.id AS store_id, s.name AS punto_vendita, s.city, s.phone
             FROM outreach_requests r
             JOIN stores s ON s.id = r.store_id
             WHERE r.next_follow_up IS NOT NULL
               AND r.next_follow_up <= CURRENT_DATE + INTERVAL 7 DAY
               AND r.status IN ("da_contattare", "contattato", "in_attesa")
             ORDER BY r.next_follow_up'
        );
    }

    /** @return array<string, list<string>> valori disponibili per i menu dei filtri */
    public static function valoriFiltri(): array
    {
        $estrai = static fn (string $colonna): array => array_column(
            Database::seleziona(
                "SELECT DISTINCT {$colonna} AS v FROM v_agenda_contatti
                 WHERE {$colonna} IS NOT NULL ORDER BY v"
            ),
            'v'
        );

        return [
            // Nomi di colonna scritti a mano, mai presi dalla richiesta HTTP.
            'citta'     => $estrai('city'),
            'catena'    => $estrai('chain_slug'),
            'tipologia' => $estrai('tipologia'),
            'stato'     => ['da_contattare', 'contattato', 'in_attesa', 'autorizzato', 'rifiutato', 'rimandato'],
        ];
    }

    /** @return array<string,int> conteggi per lo stato delle richieste */
    public static function riepilogo(): array
    {
        $righe = Database::seleziona(
            'SELECT stato_richiesta, COUNT(*) AS quante
             FROM v_agenda_contatti GROUP BY stato_richiesta'
        );

        return array_column($righe, 'quante', 'stato_richiesta');
    }
}
