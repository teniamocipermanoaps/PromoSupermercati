<?php

declare(strict_types=1);

/**
 * Stampa in JSON i punteggi calcolati dall'implementazione PHP.
 * Serve al test di parita' con il modulo Python di riferimento
 * (crawler/tests/test_footfall_parity.py). Non usato in produzione.
 */

require __DIR__ . '/../app/Support/Footfall.php';

use App\Support\Footfall;

$tipologie = ['ipermercato', 'superstore', 'discount', 'supermercato',
    'cash_and_carry', 'superette', null];
$giorni = ['2026-09-17', '2026-09-18', '2026-09-19', '2026-09-20',
    '2026-09-21', '2026-09-22', '2026-09-23', '2026-10-10'];

$inizio = new DateTimeImmutable('2026-09-17');
$fine = new DateTimeImmutable('2026-09-23');

$risultati = [];
foreach ($tipologie as $tipologia) {
    foreach ($giorni as $giorno) {
        $risultati[($tipologia ?? 'ignota') . '|' . $giorno] = Footfall::punteggio(
            new DateTimeImmutable($giorno),
            $tipologia,
            $inizio,
            $fine
        );
    }
}

echo json_encode($risultati, JSON_PRETTY_PRINT), PHP_EOL;
