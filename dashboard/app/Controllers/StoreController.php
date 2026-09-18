<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Percorsi;
use App\Core\View;
use App\Models\OutreachRepository;
use App\Models\StoreRepository;
use App\Support\Footfall;
use DateTimeImmutable;

final class StoreController
{
    public function elenco(): void
    {
        $filtri = [
            'provincia' => trim((string) ($_GET['provincia'] ?? '')),
            'citta' => trim((string) ($_GET['citta'] ?? '')),
            'catena' => trim((string) ($_GET['catena'] ?? '')),
            'tipologia' => trim((string) ($_GET['tipologia'] ?? '')),
            'cerca' => trim((string) ($_GET['cerca'] ?? '')),
        ];

        $punti = StoreRepository::elenco($filtri);
        $totale = StoreRepository::conta($filtri);

        View::rendi('store/index', [
            'titoloPagina' => 'Punti vendita',
            'punti' => $punti,
            'totale' => $totale,
            'limite' => StoreRepository::LIMITE_ELENCO,
            'filtri' => $filtri,
            'valoriFiltri' => StoreRepository::valoriFiltri(),
            'esito' => $_GET['esito'] ?? null,
        ]);
    }

    /** Salva i campi della scheda che curano le segretarie. */
    public function aggiorna(int $id): void
    {
        if (StoreRepository::trova($id) === null) {
            http_response_code(404);
            View::rendi('errore', [
                'titoloPagina' => 'Non trovato',
                'titolo' => 'Punto vendita non trovato',
                'messaggio' => 'Nessun punto vendita con identificativo ' . $id . '.',
            ]);
            return;
        }

        StoreRepository::aggiorna($id, $_POST);
        header('Location: ' . Percorsi::a('/punti-vendita/' . $id) . '?esito=scheda-salvata');
    }

    /** Registra un referente del punto vendita. */
    public function creaContatto(int $id): void
    {
        if (StoreRepository::trova($id) === null) {
            http_response_code(404);
            View::rendi('errore', [
                'titoloPagina' => 'Non trovato',
                'titolo' => 'Punto vendita non trovato',
                'messaggio' => 'Nessun punto vendita con identificativo ' . $id . '.',
            ]);
            return;
        }

        // Il modulo ha gia' i campi obbligatori, ma una POST puo' arrivare da
        // qualunque parte: un referente senza nome non serve a nessuno.
        if (trim((string) ($_POST['full_name'] ?? '')) === '') {
            header('Location: ' . Percorsi::a('/punti-vendita/' . $id) . '?esito=referente-senza-nome');
            return;
        }

        StoreRepository::creaContatto($id, $_POST);
        header('Location: ' . Percorsi::a('/punti-vendita/' . $id) . '?esito=referente-aggiunto');
    }

    public function scheda(int $id): void
    {
        $punto = StoreRepository::trova($id);
        if ($punto === null) {
            http_response_code(404);
            View::rendi('errore', [
                'titoloPagina' => 'Non trovato',
                'titolo' => 'Punto vendita non trovato',
                'messaggio' => 'Nessun punto vendita con identificativo ' . $id . '.',
            ]);
            return;
        }

        $campagne = StoreRepository::campagne($id);
        foreach ($campagne as &$campagna) {
            $campagna['giorni'] = Footfall::giorniConsigliati(
                new DateTimeImmutable((string) $campagna['inizio']),
                new DateTimeImmutable((string) $campagna['fine']),
                (string) $punto['tipologia'],
                3
            );
        }
        unset($campagna);

        View::rendi('store/show', [
            'titoloPagina' => (string) $punto['name'],
            'punto' => $punto,
            'contatti' => StoreRepository::contatti($id),
            'campagne' => $campagne,
            'richieste' => StoreRepository::richieste($id),
            'banchetti' => StoreRepository::banchetti($id),
            'resa' => StoreRepository::resa($id),
            'stati' => OutreachRepository::STATI,
            'canali' => OutreachRepository::CANALI,
        ]);
    }
}
