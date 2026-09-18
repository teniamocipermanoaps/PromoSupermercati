<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Percorsi;
use App\Core\View;
use App\Models\StallRepository;
use App\Models\StoreRepository;

final class BanchettiController
{
    public function index(): void
    {
        View::rendi('banchetti/index', [
            'titoloPagina' => 'Banchetti svolti',
            'banchetti' => StallRepository::elenco(),
            'resa' => StallRepository::resa(),
            'confronto' => StallRepository::confrontoPromo(),
            'punti' => StoreRepository::elenco(),
        ]);
    }

    public function crea(): void
    {
        if ((int) ($_POST['store_id'] ?? 0) <= 0 || ($_POST['event_date'] ?? '') === '') {
            header('Location: ' . Percorsi::a('/banchetti') . '?errore=campi-mancanti');
            return;
        }

        StallRepository::crea($_POST);
        header('Location: ' . Percorsi::a('/banchetti'));
    }
}
