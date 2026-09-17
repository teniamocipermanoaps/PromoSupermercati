<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\AgendaRepository;
use App\Support\Footfall;
use DateTimeImmutable;

final class AgendaController
{
    public function index(): void
    {
        $filtri = [
            'citta' => $_GET['citta'] ?? '',
            'catena' => $_GET['catena'] ?? '',
            'tipologia' => $_GET['tipologia'] ?? '',
            'stato' => $_GET['stato'] ?? '',
        ];

        $righe = AgendaRepository::prossime($filtri);

        // Per ogni finestra promozionale, i giorni migliori da proporre.
        foreach ($righe as &$riga) {
            $riga['giorni'] = Footfall::giorniConsigliati(
                new DateTimeImmutable((string) $riga['inizio']),
                new DateTimeImmutable((string) $riga['fine']),
                (string) $riga['tipologia'],
                3
            );
        }
        unset($riga);

        View::rendi('agenda/index', [
            'titoloPagina' => 'Agenda contatti',
            'righe' => $righe,
            'filtri' => $filtri,
            'valoriFiltri' => AgendaRepository::valoriFiltri(),
            'riepilogo' => AgendaRepository::riepilogo(),
            'daRichiamare' => AgendaRepository::daRichiamare(),
        ]);
    }
}
